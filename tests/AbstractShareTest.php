<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\ACL;
use Icewind\SMB\Exception\AlreadyExistsException;
use Icewind\SMB\Exception\FileInUseException;
use Icewind\SMB\Exception\InvalidPathException;
use Icewind\SMB\Exception\InvalidResourceException;
use Icewind\SMB\Exception\InvalidTypeException;
use Icewind\SMB\Exception\NotEmptyException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\FileInfo;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\IShare;

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

	public function tearDown(): void {
		try {
			if ($this->share) {
				try {
					$this->cleanDir($this->root);
				} catch (\Exception $e) {
					// ignore
				}
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
			['Mixed language, 日本語　が　わからか and Various _/* characters \\|” €'],
			[str_repeat('Long text with lots of characters so we get a resulting string that tests the chunked writing and reading properly', 100)]
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
		$names = array_map(function (IShare $share) {
			return $share->getName();
		}, $shares);

		$this->assertContains($this->config->share, $names);
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
	 */
	public function testMkdirInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
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
	 */
	public function testPutInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
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

	public function testGetInvalidTarget() {
		$this->expectException(InvalidResourceException::class);
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

	public function testCreateFolderInNonExistingFolder() {
		$this->expectException(NotFoundException::class);
		$this->share->mkdir($this->root . '/foo/bar');
	}

	public function testRemoveFolderInNonExistingFolder() {
		$this->expectException(NotFoundException::class);
		$this->share->rmdir($this->root . '/foo/bar');
	}

	public function testRemoveNonExistingFolder() {
		$this->expectException(NotFoundException::class);
		$this->share->rmdir($this->root . '/foo');
	}

	public function testCreateExistingFolder() {
		$this->expectException(AlreadyExistsException::class);
		$this->share->mkdir($this->root . '/bar');
		$this->share->mkdir($this->root . '/bar');
		$this->share->rmdir($this->root . '/bar');
	}

	public function testCreateFileExistingFolder() {
		$this->expectException(InvalidTypeException::class);
		$this->share->mkdir($this->root . '/bar');
		$this->share->put($this->getTextFile(), $this->root . '/bar');
		$this->share->rmdir($this->root . '/bar');
	}

	public function testCreateFileInNonExistingFolder() {
		$this->expectException(NotFoundException::class);
		$this->share->put($this->getTextFile(), $this->root . '/foo/bar');
	}

	public function testTestRemoveNonExistingFile() {
		$this->expectException(NotFoundException::class);
		$this->share->del($this->root . '/foo');
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testDownloadInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
		$this->share->get($name, '');
	}

	public function testDownloadNonExistingFile() {
		$this->expectException(NotFoundException::class);
		$this->share->get($this->root . '/foo', '/dev/null');
	}

	public function testDownloadFolder() {
		$this->expectException(InvalidTypeException::class);
		$this->share->mkdir($this->root . '/foobar');
		$this->share->get($this->root . '/foobar', '/dev/null');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testDelInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
		$this->share->del($name);
	}

	public function testRmdirFile() {
		$this->expectException(InvalidTypeException::class);
		$this->share->put($this->getTextFile(), $this->root . '/foobar');
		$this->share->rmdir($this->root . '/foobar');
		$this->share->del($this->root . '/foobar');
	}

	public function testRmdirNotEmpty() {
		$this->expectException(NotEmptyException::class);
		$this->share->mkdir($this->root . '/foobar');
		$this->share->put($this->getTextFile(), $this->root . '/foobar/asd');
		$this->share->rmdir($this->root . '/foobar');
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testRmDirInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
		$this->share->rmdir($name);
	}

	public function testDirNonExisting() {
		$this->expectException(NotFoundException::class);
		$this->share->dir('/foobar/asd');
	}

	public function testRmDirNonExisting() {
		$this->expectException(NotFoundException::class);
		$this->share->rmdir('/foobar/asd');
	}

	public function testRenameNonExisting() {
		$this->expectException(NotFoundException::class);
		$this->share->rename('/foobar/asd', '/foobar/bar');
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testRenameInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
		$this->share->rename($name, $name . '_');
	}

	public function testRenameTargetNonExisting() {
		$this->expectException(NotFoundException::class);
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
	 * @dataProvider nameAndDataProvider
	 */
	public function testReadStreamChunked($name, $text) {
		$sourceFile = $this->getTextFile($text);
		$this->share->put($sourceFile, $this->root . '/' . $name);
		$fh = $this->share->read($this->root . '/' . $name);
		$content = "";
		while (!feof($fh)) {
			$content .= fread($fh, 8192);
		}
		fclose($fh);
		$this->share->del($this->root . '/' . $name);

		$this->assertEquals(file_get_contents($sourceFile), $content);
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testReadStreamInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
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

	/**
	 * @dataProvider nameAndDataProvider
	 */
	public function testWriteStreamChunked($name, $text) {
		$fh = $this->share->write($this->root . '/' . $name);

		foreach (str_split($text, 8192) as $chunk) {
			fwrite($fh, $chunk);
		}
		fclose($fh);

		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/' . $name, $tmpFile1);
		$this->assertEquals($text, file_get_contents($tmpFile1));
		$this->share->del($this->root . '/' . $name);
		unlink($tmpFile1);
	}

	public function testAppendStream() {
		$name = 'foo.txt';
		$fh = $this->share->append($this->root . '/' . $name);
		fwrite($fh, 'foo');
		fclose($fh);

		$fh = $this->share->append($this->root . '/' . $name);
		fwrite($fh, 'bar');
		fclose($fh);

		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/' . $name, $tmpFile1);
		$this->assertEquals('foobar', file_get_contents($tmpFile1));
		$this->share->del($this->root . '/' . $name);
		unlink($tmpFile1);
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testWriteStreamInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
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
		$this->assertFalse($dirEntry->isHidden());

		if ($dir[0]->getName() === 'file.txt') {
			$fileEntry = $dir[0];
		} else {
			$fileEntry = $dir[1];
		}
		$this->assertFalse($fileEntry->isDirectory());
		$this->assertFalse($fileEntry->isReadOnly());
		$this->assertFalse($fileEntry->isHidden());
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testDirInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
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
	 */
	public function testStatInvalidPath($name) {
		$this->expectException(InvalidPathException::class);
		$this->share->stat($name);
	}

	public function testStatNonExisting() {
		$this->expectException(NotFoundException::class);
		$this->share->stat($this->root . '/fo.txt');
	}

	/**
	 * note setting archive and system bit is not supported
	 *
	 * @dataProvider nameProvider
	 */
	public function testSetMode($name) {
		$this->markTestSkipped("mode detection is mostly broken with newer libsmbclient versions");
		return;
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
		$this->assertTrue($this->share->del($this->root . '/foo.txt'));
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

	public function testMoveIntoSelf() {
		$this->expectException(FileInUseException::class);
		$this->share->mkdir($this->root . '/folder');
		$this->share->rename($this->root . '/folder', $this->root . '/folder/subfolder');
	}

	public function testDirACL() {
		$this->share->mkdir($this->root . "/test");
		$listing = $this->share->dir($this->root);

		$this->assertCount(1, $listing);
		$acls = $listing[0]->getAcls();
		$acl = $acls['Everyone'];
		$this->assertEquals($acl->getType(), ACL::TYPE_ALLOW);
		$this->assertEquals(ACL::MASK_READ, $acl->getMask() & ACL::MASK_READ);
	}

	public function testStatACL() {
		$this->share->mkdir($this->root . "/test");
		$info = $this->share->stat($this->root);

		$acls = $info->getAcls();
		$acl = $acls['Everyone'];
		$this->assertEquals($acl->getType(), ACL::TYPE_ALLOW);
		$this->assertEquals(ACL::MASK_READ, $acl->getMask() & ACL::MASK_READ);
	}
}
