<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\NativeServer;

class NativeStream extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \Icewind\SMB\Server $server
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\NativeShare $share
	 */
	protected $share;

	/**
	 * @var string $root
	 */
	protected $root;

	protected $config;

	public function setUp() {
		if (!function_exists('smbclient_state_new')) {
			$this->markTestSkipped('libsmbclient php extension not installed');
		}
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NativeServer($this->config->host, $this->config->user, $this->config->password);
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

	public function tearDown() {
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
