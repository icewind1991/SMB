<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\NativeServer;

class NativeShare extends Share {

	/**
	 * @var \Icewind\SMB\NativeShare $share
	 */
	protected $share;

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

	public function testRestoreErrorHandler() {
		$handlerCalled = false;
		set_error_handler(function () use (&$handlerCalled) {
			$handlerCalled = true;
		});

		$this->share->dir($this->root);

		trigger_error('dummy');
		$this->assertTrue($handlerCalled);
		restore_error_handler();
	}
}
