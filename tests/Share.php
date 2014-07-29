<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

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

	public function nameProvider() {
		// / ? < > \ : * | " are illegal characters in path on windows, no use trying to get them working
		return array(
			array('simple'),
			array('with spaces_and-underscores'),
			array("single'quote'"),
			array('$as#d€££Ö€ßœĚęĘĞĜΣΥΦΩΫΫ')
		);
	}

	public function fileDataProvider() {
		return array(
			array('Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua'),
			array('Mixed language, 日本語　が　わからか and Various _/* characters \\|” €')
		);
	}

	public function nameAndDataProvider() {
		$names = $this->nameProvider();
		$data = $this->fileDataProvider();
		$result = array();
		foreach ($names as $name) {
			foreach ($data as $text) {
				$result[] = array($name[0], $text[0]);
			}
		}
		return $result;
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

	private function getTextFile($text = '') {
		if (!$text) {
			$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		}
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

	/**
	 * @dataProvider fileDataProvider
	 */
	public function testFile($text) {
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

	/**
	 * @dataProvider nameProvider
	 */
	public function testEscaping($name) {
		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		file_put_contents($tmpFile1, $text);

		$this->share->mkdir($this->root . '/' . $name);
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

	/**
	 * @dataProvider nameAndDataProvider
	 */
	public function testReadStream($name, $text) {
		$sourceFile = $this->getTextFile($text);
		$this->share->put($sourceFile, $this->root . '/' . $name);
		$fh = $this->share->read($this->root . '/' . $name);
		$content = stream_get_contents($fh);
		fclose($fh);
		$this->share->del($this->root . '/' . $name);

		$this->assertEquals(file_get_contents($sourceFile), $content);
	}

	/**
	 * @dataProvider nameAndDataProvider
	 */
	public function testWriteStream($name, $text) {
		$fh = $this->share->write($this->root . '/' . $name);
		fwrite($fh, $text);
		fclose($fh);

		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/' . $name, $tmpFile1);
		$this->assertEquals($text, file_get_contents($tmpFile1));
		$this->share->del($this->root . '/' . $name);
		unlink($tmpFile1);
	}
}
