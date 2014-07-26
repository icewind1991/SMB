<?php

namespace Icewind\SMB\Test;

use Icewind\SMB\Server;

class Share extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \Icewind\SMB\Server $server
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\Share $share
	 */
	protected $share;

	/**
	 * @var string $root
	 */
	protected $root;

	protected $config;

	public function setUp() {
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new Server($this->config->host, $this->config->user, $this->config->password);
		$this->share = $this->server->getShare($this->config->share);
		if ($this->config->root) {
			$this->root = '/' . $this->config->root . '/' . uniqid();
		} else {
			$this->root = '/' . uniqid();
		}
		$this->share->mkdir($this->root);
	}

	public function tearDown() {
		if ($this->share) {
			$this->cleanDir($this->root);
		}
		unset($this->share);
	}

	public function cleanDir($dir) {
		$content = $this->share->dir($dir);
		foreach ($content as $name => $metadata) {
			if ($metadata['type'] === 'dir') {
				$this->cleanDir($dir . '/' . $name);
			} else {
				$this->share->del($dir . '/' . $name);
			}
		}
		$this->share->rmdir($dir);
	}

	private function getTextFile() {
		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$file = tempnam('/tmp', 'smb_test_');
		file_put_contents($file, $text);
		return $file;
	}

	public function testListShares() {
		$shares = $this->server->listShares();
		foreach ($shares as $share) {
			if ($share->getName() === $this->config->share) {
				return;
			}
		}
		$this->fail('Share "' . $this->config->share . '" not found');
	}

	public function testDirectory() {
		$this->assertEquals(array(), $this->share->dir($this->root));

		$this->share->mkdir($this->root . '/foo');
		$dirs = $this->share->dir($this->root);
		$this->assertEquals(1, count($dirs));
		$this->assertArrayHasKey('foo', $dirs);

		$this->share->rename($this->root . '/foo', $this->root . '/bar');

		$dirs = $this->share->dir($this->root);
		$this->assertEquals(1, count($dirs));
		$this->assertArrayHasKey('bar', $dirs);

		$this->share->rmdir($this->root . '/bar');
		$this->assertEquals(array(), $this->share->dir($this->root));
	}

	public function testFile() {
		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$size = strlen($text);
		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		file_put_contents($tmpFile1, $text);

		$this->share->put($tmpFile1, $this->root . '/lorem.txt');
		unlink($tmpFile1);

		$files = $this->share->dir($this->root);
		$this->assertEquals(1, count($files));
		$this->assertArrayHasKey('lorem.txt', $files);
		$this->assertEquals($files['lorem.txt']['size'], $size);

		$this->share->rename($this->root . '/lorem.txt', $this->root . '/foo.txt');

		$files = $this->share->dir($this->root);
		$this->assertEquals(1, count($files));
		$this->assertArrayHasKey('foo.txt', $files);

		$tmpFile2 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/foo.txt', $tmpFile2);

		$this->assertEquals($text, file_get_contents($tmpFile2));
		unlink($tmpFile2);

		$this->share->del($this->root . '/foo.txt');
		$this->assertEquals(array(), $this->share->dir($this->root));
	}

	public function testEscaping() {
		// / ? < > \ : * | ” are illegal characters in path on windows, no use trying to get them working
		$names = array('simple', 'with spaces', "single'quote'", '$as#d', '€', '££Ö€ßœĚęĘĞĜΣΥΦΩΫΫ', '_under - score');

		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		file_put_contents($tmpFile1, $text);

		foreach ($names as $name) {
			$this->share->mkdir($this->root . '/' . $name);
			$this->share->connect();
			$dir = $this->share->dir($this->root);
			$this->assertArrayHasKey($name, $dir);
			$this->assertEquals('dir', $dir[$name]['type']);
			$this->assertEquals(array(), $this->share->dir($this->root . '/' . $name));

			$this->share->put($tmpFile1, $this->root . '/' . $name . '/foo.txt');
			$dir = $this->share->dir($this->root . '/' . $name);
			$this->assertArrayHasKey('foo.txt', $dir);

			$tmpFile2 = tempnam('/tmp', 'smb_test_');
			$this->share->get($this->root . '/' . $name . '/foo.txt', $tmpFile2);
			$this->assertEquals($text, file_get_contents($tmpFile2));
			unlink($tmpFile2);

			$this->share->rename($this->root . '/' . $name . '/foo.txt', $this->root . '/' . $name . '/bar.txt');
			$dir = $this->share->dir($this->root . '/' . $name);
			$this->assertArrayHasKey('bar.txt', $dir);
			$this->assertEquals('file', $dir['bar.txt']['type']);

			$this->share->del($this->root . '/' . $name . '/bar.txt');
			$this->assertEquals(array(), $this->share->dir($this->root . '/' . $name));
			$this->share->rmdir($this->root . '/' . $name);
			$this->assertEquals(array(), $this->share->dir($this->root));

			$this->share->put($tmpFile1, $this->root . '/' . $name);
			$this->assertArrayHasKey($name, $this->share->dir($this->root));

			$tmpFile2 = tempnam('/tmp', 'smb_test_');
			$this->share->get($this->root . '/' . $name, $tmpFile2);
			$this->assertEquals($text, file_get_contents($tmpFile2));
			unlink($tmpFile2);

			$this->share->del($this->root . '/' . $name);

			$tmpFile2 = tempnam('/tmp', 'smb_test_' . $name);
			$this->share->put($tmpFile2, $this->root . '/' . $name);
			$this->assertArrayHasKey($name, $this->share->dir($this->root));
			$this->share->del($this->root . '/' . $name);
			unlink($tmpFile2);

			$this->assertEquals(array(), $this->share->dir($this->root));
		}

		unlink($tmpFile1);
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testCreateFolderInNonExistingFolder() {
		$this->share->mkdir($this->root . '/foo/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testRemoveFolderInNonExistingFolder() {
		$this->share->rmdir($this->root . '/foo/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testRemoveNonExistingFolder() {
		$this->share->rmdir($this->root . '/foo');
	}

	/**
	 * @expectedException \Icewind\SMB\AlreadyExistsException
	 */
	public function testCreateExistingFolder() {
		$this->share->mkdir($this->root . '/bar');
		$this->share->mkdir($this->root . '/bar');
		$this->share->rmdir($this->root . '/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\InvalidTypeException
	 */
	public function testCreateFileExistingFolder() {
		$this->share->mkdir($this->root . '/bar');
		$this->share->put($this->getTextFile(), $this->root . '/bar');
		$this->share->rmdir($this->root . '/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testCreateFileInNonExistingFolder() {
		$this->share->put($this->getTextFile(), $this->root . '/foo/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testTestRemoveNonExistingFile() {
		$this->share->del($this->root . '/foo');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testDownloadNonExistingFile() {
		$this->share->get($this->root . '/foo', '/dev/null');
	}

	/**
	 * @expectedException \Icewind\SMB\InvalidTypeException
	 */
	public function testDownloadFolder() {
		$this->share->mkdir($this->root . '/foobar');
		$this->share->get($this->root . '/foobar', '/dev/null');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @expectedException \Icewind\SMB\InvalidTypeException
	 */
	public function testDelFolder() {
		$this->share->mkdir($this->root . '/foobar');
		$this->share->del($this->root . '/foobar');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @expectedException \Icewind\SMB\InvalidTypeException
	 */
	public function testRmdirFile() {
		$this->share->put($this->getTextFile(), $this->root . '/foobar');
		$this->share->rmdir($this->root . '/foobar');
		$this->share->del($this->root . '/foobar');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testDirNonExisting() {
		$this->share->dir('/foobar/asd');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testRmDirNonExisting() {
		$this->share->rmdir('/foobar/asd');
	}

	public function testModifiedDate() {
		$now = time();
		$this->share->put($this->getTextFile(), $this->root . '/foo.txt');
		$dir = $this->share->dir($this->root);
		$mtime = $dir['foo.txt']['time'];
		$this->assertTrue(abs($now - $mtime) <= 1, 'Modified time differs by ' . abs($now - $mtime) . ' seconds');
		$this->share->del($this->root . '/foo.txt');
	}

	public function testListRoot() {
		$files = $this->share->dir('');
		$this->assertGreaterThan(0, count($files));
	}

	public function testReadStream() {
		$sourceFile = $this->getTextFile();
		$this->share->put($sourceFile, $this->root . '/foobar');
		$fh = $this->share->read($this->root . '/foobar');
		$content = stream_get_contents($fh);
		fclose($fh);
		$this->share->del($this->root . '/foobar');

		$this->assertEquals(file_get_contents($sourceFile), $content);
	}

	public function testWriteStream() {
		$fh = $this->share->write($this->root . '/foobar', 'qwerty');
		fwrite($fh, 'qwerty');
		fclose($fh);
//		sleep(5);


		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/foobar', $tmpFile1);
		$this->assertEquals('qwerty', file_get_contents($tmpFile1));
		unlink($tmpFile1);
	}
}
