<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\Exception\FileInUseException;
use Icewind\SMB\Exception\InvalidPathException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\FileInfo;
use Icewind\SMB\IFileInfo;

abstract class AbstractShareTest extends TestCase {
	/**
	 * @var \Icewind\SMB\IServer $server
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
		try {
			if ($this->share) {
				$this->cleanDir($this->root);
			}
			unset($this->share);
		} catch (\Exception $e) {
			unset($this->share);
			throw $e;
		}
	}

	public function nameProvider() {
		return [
			['simple'],
			['with spaces_and-underscores'],
			["single'quote'"],
			["foo ; asd -- bar"],
			['日本語'],
			['url %2F +encode'],
			['a somewhat longer filename than the other with more charaters as the all the other filenames'],
			['$as#d€££Ö€ßœĚęĘĞĜΣΥΦΩΫ']
		];
	}

	public function invalidPathProvider() {
		// / ? < > \ : * | " are illegal characters in path on windows
		return [
			["new\nline"],
			["\rreturn"],
			['null' . chr(0) . 'byte'],
			['foo?bar'],
			['foo<bar>'],
			['foo:bar'],
			['foo*bar'],
			['foo|bar'],
			['foo"bar"']
		];
	}

	public function fileDataProvider() {
		return [
			['Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua'],
			['Mixed language, 日本語　が　わからか and Various _/* characters \\|” €']
		];
	}

	public function nameAndDataProvider() {
		$names = $this->nameProvider();
		$data = $this->fileDataProvider();
		$result = [];
		foreach ($names as $name) {
			foreach ($data as $text) {
				$result[] = [$name[0], $text[0]];
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

	public function testRootStartsEmpty() {
		$this->assertEquals([], $this->share->dir($this->root));
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testMkdir($name) {
		$this->share->mkdir($this->root . '/' . $name);
		$dirs = $this->share->dir($this->root);
		$this->assertCount(1, $dirs);
		$this->assertEquals($name, $dirs[0]->getName());
		$this->assertTrue($dirs[0]->isDirectory());
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testMkdirInvalidPath($name) {
		$this->share->mkdir($this->root . '/' . $name);
		$dirs = $this->share->dir($this->root);
		$this->assertCount(1, $dirs);
		$this->assertEquals($name, $dirs[0]->getName());
		$this->assertTrue($dirs[0]->isDirectory());
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testRenameDirectory($name) {
		$this->share->mkdir($this->root . '/' . $name);
		$this->share->rename($this->root . '/' . $name, $this->root . '/' . $name . '_rename');
		$dirs = $this->share->dir($this->root);
		$this->assertEquals(1, count($dirs));
		$this->assertEquals($name . '_rename', $dirs[0]->getName());
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testRmdir($name) {
		$this->share->mkdir($this->root . '/' . $name);
		$this->share->rmdir($this->root . '/' . $name);
		$this->assertCount(0, $this->share->dir($this->root));
	}

	/**
	 * @dataProvider nameAndDataProvider
	 */
	public function testPut($name, $text) {
		$tmpFile = $this->getTextFile($text);
		$size = filesize($tmpFile);

		$this->share->put($tmpFile, $this->root . '/' . $name);
		unlink($tmpFile);

		$files = $this->share->dir($this->root);
		$this->assertCount(1, $files);
		$this->assertEquals($name, $files[0]->getName());
		$this->assertEquals($size, $files[0]->getSize());
		$this->assertFalse($files[0]->isDirectory());
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testPutInvalidPath($name) {
		$tmpFile = $this->getTextFile('foo');

		try {
			$this->share->put($tmpFile, $this->root . '/' . $name);
		} catch (InvalidPathException $e) {
			unlink($tmpFile);
			throw $e;
		}
		unlink($tmpFile);
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testRenameFile($name) {
		$tmpFile = $this->getTextFile();

		$this->share->put($tmpFile, $this->root . '/' . $name);
		unlink($tmpFile);

		$this->assertTrue($this->share->rename($this->root . '/' . $name, $this->root . '/' . $name . '_renamed'));

		$files = $this->share->dir($this->root);
		$this->assertEquals(1, count($files));
		$this->assertEquals($name . '_renamed', $files[0]->getName());
	}

	/**
	 * @dataProvider nameAndDataProvider
	 */
	public function testGet($name, $text) {
		$tmpFile = $this->getTextFile($text);

		$this->share->put($tmpFile, $this->root . '/' . $name);
		unlink($tmpFile);

		$targetFile = tempnam('/tmp', 'smb_test_');
		$this->assertTrue($this->share->get($this->root . '/' . $name, $targetFile));

		$this->assertEquals($text, file_get_contents($targetFile));
		unlink($targetFile);
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\InvalidResourceException
	 */
	public function testGetInvalidTarget() {
		$name = 'test.txt';
		$text = 'dummy';
		$tmpFile = $this->getTextFile($text);

		$this->share->put($tmpFile, $this->root . '/' . $name);
		unlink($tmpFile);

		$this->share->get($this->root . '/' . $name, '/non/existing/file');
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testDel($name) {
		$tmpFile = $this->getTextFile();

		$this->share->put($tmpFile, $this->root . '/' . $name);
		unlink($tmpFile);

		$this->share->del($this->root . '/' . $name);
		$this->assertCount(0, $this->share->dir($this->root));
	}

	public function testNotFoundExceptionPath() {
		try {
			$this->share->mkdir($this->root . '/foo/bar');
			$this->fail();
		} catch (NotFoundException $e) {
			$this->assertEquals($this->root . '/foo/bar', $e->getPath());
		}
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testCreateFolderInNonExistingFolder() {
		$this->share->mkdir($this->root . '/foo/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testRemoveFolderInNonExistingFolder() {
		$this->share->rmdir($this->root . '/foo/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testRemoveNonExistingFolder() {
		$this->share->rmdir($this->root . '/foo');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\AlreadyExistsException
	 */
	public function testCreateExistingFolder() {
		$this->share->mkdir($this->root . '/bar');
		$this->share->mkdir($this->root . '/bar');
		$this->share->rmdir($this->root . '/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function testCreateFileExistingFolder() {
		$this->share->mkdir($this->root . '/bar');
		$this->share->put($this->getTextFile(), $this->root . '/bar');
		$this->share->rmdir($this->root . '/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testCreateFileInNonExistingFolder() {
		$this->share->put($this->getTextFile(), $this->root . '/foo/bar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testTestRemoveNonExistingFile() {
		$this->share->del($this->root . '/foo');
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testDownloadInvalidPath($name) {
		$this->share->get($name, '');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testDownloadNonExistingFile() {
		$this->share->get($this->root . '/foo', '/dev/null');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function testDownloadFolder() {
		$this->share->mkdir($this->root . '/foobar');
		$this->share->get($this->root . '/foobar', '/dev/null');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function testDelFolder() {
		$this->share->mkdir($this->root . '/foobar');
		$this->share->del($this->root . '/foobar');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testDelInvalidPath($name) {
		$this->share->del($name);
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function testRmdirFile() {
		$this->share->put($this->getTextFile(), $this->root . '/foobar');
		$this->share->rmdir($this->root . '/foobar');
		$this->share->del($this->root . '/foobar');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotEmptyException
	 */
	public function testRmdirNotEmpty() {
		$this->share->mkdir($this->root . '/foobar');
		$this->share->put($this->getTextFile(), $this->root . '/foobar/asd');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testRmDirInvalidPath($name) {
		$this->share->rmdir($name);
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testDirNonExisting() {
		$this->share->dir('/foobar/asd');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testRmDirNonExisting() {
		$this->share->rmdir('/foobar/asd');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testRenameNonExisting() {
		$this->share->rename('/foobar/asd', '/foobar/bar');
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testRenameInvalidPath($name) {
		$this->share->rename($name, $name . '_');
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
	 */
	public function testRenameTargetNonExisting() {
		$txt = $this->getTextFile();
		$this->share->put($txt, $this->root . '/foo.txt');
		unlink($txt);
		$this->share->rename($this->root . '/foo.txt', $this->root . '/bar/foo.txt');
	}

	public function testModifiedDate() {
		$now = time();
		$this->share->put($this->getTextFile(), $this->root . '/foo.txt');
		$dir = $this->share->dir($this->root);
		$mtime = $dir[0]->getMTime();
		$this->assertTrue(abs($now - $mtime) <= 2, 'Modified time differs by ' . abs($now - $mtime) . ' seconds');
		$this->share->del($this->root . '/foo.txt');
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
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testReadStreamInvalidPath($name) {
		$this->share->read($name);
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

	public function testAppendStream() {
		$fh = $this->share->append($this->root . '/' . $name);
		fwrite($fh, 'foo');
		fclose($fh);

		$fh = $this->share->append($this->root . '/' . $name);
		fwrite($fh, 'bar');
		fclose($fh);

		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		$this->assertEquals('foobar', file_get_contents($tmpFile1));
		$this->share->del($this->root . '/' . $name);
		unlink($tmpFile1);
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testWriteStreamInvalidPath($name) {
		$fh = $this->share->write($this->root . '/' . $name);
		fwrite($fh, 'foo');
		fclose($fh);
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
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testDirInvalidPath($name) {
		$this->share->dir($name);
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
	 * @dataProvider invalidPathProvider
	 * @expectedException \Icewind\SMB\Exception\InvalidPathException
	 */
	public function testStatInvalidPath($name) {
		$this->share->stat($name);
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\NotFoundException
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

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_NORMAL);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_READONLY);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertTrue($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_ARCHIVE);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertTrue($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_READONLY | IFileInfo::MODE_ARCHIVE);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertTrue($info->isReadOnly());
		$this->assertTrue($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_HIDDEN);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertTrue($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_SYSTEM);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertTrue($info->isSystem());
		$this->assertFalse($info->isHidden());

		$this->share->setMode($this->root . '/' . $name, IFileInfo::MODE_NORMAL);
		$info = $this->share->stat($this->root . '/' . $name);
		$this->assertFalse($info->isReadOnly());
		$this->assertFalse($info->isArchived());
		$this->assertFalse($info->isSystem());
		$this->assertFalse($info->isHidden());
	}

	public function pathProvider() {
		// / ? < > \ : * | " are illegal characters in path on windows
		return [
			['dir/sub/foo.txt'],
			['bar.txt'],
			["single'quote'/sub/foo.txt"],
			['日本語/url %2F +encode/asd.txt'],
			[
				'a somewhat longer folder than the other with more charaters as the all the other filenames/' .
				'followed by a somewhat long file name after that.txt'
			]
		];
	}

	/**
	 * @dataProvider pathProvider
	 */
	public function testSubDirs($path) {
		$dirs = explode('/', $path);
		$name = array_pop($dirs);
		$fullPath = '';
		foreach ($dirs as $dir) {
			$fullPath .= '/' . $dir;
			$this->share->mkdir($this->root . $fullPath);
		}
		$txtFile = $this->getTextFile();
		$size = filesize($txtFile);
		$this->share->put($txtFile, $this->root . $fullPath . '/' . $name);
		unlink($txtFile);
		$info = $this->share->stat($this->root . $fullPath . '/' . $name);
		$this->assertEquals($size, $info->getSize());
		$this->assertFalse($info->isHidden());
	}

	public function testDelAfterStat() {
		$name = 'foo.txt';
		$txtFile = $this->getTextFile();

		$this->share->put($txtFile, $this->root . '/' . $name);
		unlink($txtFile);

		$this->share->stat($this->root . '/' . $name);
		$this->share->del($this->root . '/foo.txt');
	}

	/**
	 * @param $name
	 * @dataProvider nameProvider
	 */
	public function testDirPaths($name) {
		$txtFile = $this->getTextFile();
		$this->share->mkdir($this->root . '/' . $name);
		$this->share->put($txtFile, $this->root . '/' . $name . '/' . $name);
		unlink($txtFile);

		$content = $this->share->dir($this->root . '/' . $name);
		$this->assertCount(1, $content);
		$this->assertEquals($name, $content[0]->getName());
	}

	public function testStatRoot() {
		$info = $this->share->stat('/');
		$this->assertInstanceOf('\Icewind\SMB\IFileInfo', $info);
	}

	/**
	* @expectedException \Icewind\SMB\Exception\FileInUseException
	 */
	public function testMoveIntoSelf() {
		$this->share->mkdir($this->root . '/folder');
		$this->share->rename($this->root . '/folder', $this->root . '/folder/subfolder');
	}
}
