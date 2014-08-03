<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\FileInfo;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\IShare;

abstract class AbstractShare extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \Icewind\SMB\Server $server
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\IShare $share
	 */
	protected $share;

	/**
	 * @var string $root
	 */
	protected $root;

	protected $config;

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
		foreach ($content as $metadata) {
			if ($metadata->isDirectory()) {
				$this->cleanDir($metadata->getPath());
			} else {
				$this->share->del($metadata->getPath());
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
		$this->assertCount(1, $dirs);
		$this->assertEquals('foo', $dirs[0]->getName());

		$this->share->rename($this->root . '/foo', $this->root . '/bar');

		$dirs = $this->share->dir($this->root);
		$this->assertEquals(1, count($dirs));
		$this->assertEquals('bar', $dirs[0]->getName());

		$this->share->rmdir($this->root . '/bar');
		$this->assertCount(0, $this->share->dir($this->root));
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
		$this->assertCount(1, $files);
		$this->assertEquals('lorem.txt', $files[0]->getName());
		$this->assertEquals($size, $files[0]->getSize());

		$this->share->rename($this->root . '/lorem.txt', $this->root . '/foo.txt');

		$files = $this->share->dir($this->root);
		$this->assertEquals(1, count($files));
		$this->assertEquals('foo.txt', $files[0]->getName());

		$tmpFile2 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/foo.txt', $tmpFile2);

		$this->assertEquals($text, file_get_contents($tmpFile2));
		unlink($tmpFile2);

		$this->share->del($this->root . '/foo.txt');
		$this->assertCount(0, $this->share->dir($this->root));
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
		$this->assertEquals($name, $dir[0]->getName());
		$this->assertTrue($dir[0]->isDirectory());
		$this->assertCount(0, $this->share->dir($this->root . '/' . $name));

		$this->share->put($tmpFile1, $this->root . '/' . $name . '/foo.txt');
		$dir = $this->share->dir($this->root . '/' . $name);
		$this->assertEquals('foo.txt', $dir[0]->getName());

		$tmpFile2 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/' . $name . '/foo.txt', $tmpFile2);
		$this->assertEquals($text, file_get_contents($tmpFile2));
		unlink($tmpFile2);

		$this->share->rename($this->root . '/' . $name . '/foo.txt', $this->root . '/' . $name . '/bar.txt');
		$dir = $this->share->dir($this->root . '/' . $name);
		$this->assertEquals('bar.txt', $dir[0]->getName());
		$this->assertFalse($dir[0]->isDirectory());

		$this->share->del($this->root . '/' . $name . '/bar.txt');
		$this->assertCount(0, $this->share->dir($this->root . '/' . $name));
		$this->share->rmdir($this->root . '/' . $name);
		$this->assertCount(0, $this->share->dir($this->root));

		$this->share->put($tmpFile1, $this->root . '/' . $name);
		$dir = $this->share->dir($this->root);
		$this->assertEquals($name, $dir[0]->getName());

		$tmpFile2 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/' . $name, $tmpFile2);
		$this->assertEquals($text, file_get_contents($tmpFile2));
		unlink($tmpFile2);

		$this->share->del($this->root . '/' . $name);

		$tmpFile2 = tempnam('/tmp', 'smb_test_' . $name);
		$this->share->put($tmpFile2, $this->root . '/' . $name);
		$dir = $this->share->dir($this->root);
		$this->assertEquals($name, $dir[0]->getName());
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

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testRenameNonExisting() {
		$this->share->rename('/foobar/asd', '/foobar/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testRenameTargetNonExisting() {
		$txt= $this->getTextFile();
		$this->share->put($txt, $this->root . '/foo.txt');
		unlink($txt);
		$this->share->rename($this->root . '/foo.txt', $this->root . '/bar/foo.txt');
	}

	public function testModifiedDate() {
		$now = time();
		$this->share->put($this->getTextFile(), $this->root . '/foo.txt');
		$dir = $this->share->dir($this->root);
		$mtime = $dir[0]->getMTime();
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

	public function testDir() {
		$txtFile = $this->getTextFile();

		$this->share->mkdir($this->root . '/dir');
		$this->share->put($txtFile, $this->root . '/file.txt');
		unlink($txtFile);

		$dir = $this->share->dir($this->root);
		if ($dir[0]->getName() === 'dir') {
			$dirEntry = $dir[0];
		} else {
			$dirEntry = $dir[1];
		}
		$this->assertTrue($dirEntry->isDirectory());
		$this->assertFalse($dirEntry->isReadOnly());
		$this->assertFalse($dirEntry->isReadOnly());

		if ($dir[0]->getName() === 'file.txt') {
			$fileEntry = $dir[0];
		} else {
			$fileEntry = $dir[1];
		}
		$this->assertFalse($fileEntry->isDirectory());
		$this->assertFalse($fileEntry->isReadOnly());
		$this->assertFalse($fileEntry->isReadOnly());
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testStat($name) {
		$txtFile = $this->getTextFile();
		$size = filesize($txtFile);

		$this->share->put($txtFile, $this->root . '/' . $name);
		unlink($txtFile);

		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertEquals($size, $info->getSize());
	}

	/**
	 * @expectedException \Icewind\SMB\NotFoundException
	 */
	public function testStatNonExisting() {
		$this->share->stat($this->root . '/fo.txt');
	}

	/**
	 * note setting archive and system bit is not supported
	 *
	 * @dataProvider nameProvider
	 */
	public function testSetMode($name) {
		$txtFile = $this->getTextFile();

		$this->share->put($txtFile, $this->root . '/' . $name);

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_NORMAL);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_READONLY);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertTrue($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_ARCHIVE);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertTrue($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_READONLY | FileInfo::MODE_ARCHIVE);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertTrue($info->isReadOnly());
		$this->assertTrue($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_HIDDEN);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertTrue($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_SYSTEM);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertTrue($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, FileInfo::MODE_NORMAL);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());
	}
}
