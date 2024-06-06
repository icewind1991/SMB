<?php
/**
 * SPDX-FileCopyrightText: 2014 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\Native\NativeServer;
use Icewind\SMB\Options;
use Icewind\SMB\System;
use Icewind\SMB\TimeZoneProvider;

class NativeStreamTest extends TestCase {
	/**
	 * @var \Icewind\SMB\IServer $server
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\Native\NativeShare $share
	 */
	protected $share;

	/**
	 * @var string $root
	 */
	protected $root;

	protected $config;

	public function setUp(): void {
		$this->requireBackendEnv('libsmbclient');
		if (!function_exists('smbclient_state_new')) {
			$this->markTestSkipped('libsmbclient php extension not installed');
		}
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NativeServer(
			$this->config->host,
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			new System(),
			new TimeZoneProvider(new System()),
			new Options()
		);
		$this->share = $this->server->getShare($this->config->share);
		if ($this->config->root) {
			$this->root = '/' . $this->config->root . '/' . uniqid();
		} else {
			$this->root = '/' . uniqid();
		}
		$this->share->mkdir($this->root);
	}

	private function getTextFile() {
		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$file = tempnam('/tmp', 'smb_test_');
		file_put_contents($file, $text);
		return $file;
	}

	public function testSeekTell() {
		$sourceFile = $this->getTextFile();
		$this->share->put($sourceFile, $this->root . '/foobar');
		$fh = $this->share->read($this->root . '/foobar');
		$content = fread($fh, 3);
		$this->assertEquals('Lor', $content);

		fseek($fh, -2, SEEK_CUR);

		$content = fread($fh, 3);
		$this->assertEquals('ore', $content);

		fseek($fh, 3, SEEK_SET);

		$content = fread($fh, 3);
		$this->assertEquals('em ', $content);

		fseek($fh, -3, SEEK_END);

		$content = fread($fh, 3);
		$this->assertEquals('qua', $content);

		fseek($fh, -3, SEEK_END);
		$this->assertEquals(120, ftell($fh));
	}

	public function testStat() {
		$sourceFile = $this->getTextFile();
		$this->share->put($sourceFile, $this->root . '/foobar');
		$fh = $this->share->read($this->root . '/foobar');
		$stat = fstat($fh);
		$this->assertEquals(filesize($sourceFile), $stat['size']);
		unlink($sourceFile);
	}

	public function testTruncate() {
		$fh = $this->share->write($this->root . '/foobar');
		fwrite($fh, 'foobar');
		ftruncate($fh, 3);
		fclose($fh);

		$fh = $this->share->read($this->root . '/foobar');
		$this->assertEquals('foo', stream_get_contents($fh));
	}

	public function testEOF() {
		$fh = $this->share->write($this->root . '/foobar');
		fwrite($fh, 'foobar');
		fclose($fh);

		$fh = $this->share->read($this->root . '/foobar');
		fread($fh, 3);
		$this->assertFalse(feof($fh));
		fread($fh, 5);
		$this->assertTrue(feof($fh));
	}

	public function testLockUnsupported() {
		$fh = $this->share->write($this->root . '/foobar');
		$this->assertFalse(flock($fh, LOCK_SH));
	}

	public function testSetOptionUnsupported() {
		$fh = $this->share->write($this->root . '/foobar');
		$this->assertFalse(stream_set_blocking($fh, false));
	}

	public function tearDown(): void {
		if ($this->share) {
			$this->cleanDir($this->root);
		}
		unset($this->share);
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
}
