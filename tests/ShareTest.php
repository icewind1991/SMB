<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\ConnectException;
use Icewind\SMB\Exception\DependencyException;
use Icewind\SMB\Options;
use Icewind\SMB\System;
use Icewind\SMB\TimeZoneProvider;
use Icewind\SMB\Wrapped\Server as NormalServer;

class ShareTest extends AbstractShareTest {
	public function setUp(): void {
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NormalServer(
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

	public function testAppendStream() {
		$this->expectException(DependencyException::class);
		$this->share->append($this->root . '/foo');
	}

	public function testHostEscape() {
		$this->expectException(ConnectException::class);
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NormalServer(
			$this->config->host . ';asd',
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			new System(),
			new TimeZoneProvider(new System()),
			new Options()
		);
		$share = $this->server->getShare($this->config->share);
		$share->dir($this->root);
	}
}
