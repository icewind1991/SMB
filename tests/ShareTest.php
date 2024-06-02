<?php
/**
 * SPDX-FileCopyrightText: 2014 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: MIT
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
	public function getServerClass(): string {
		$this->requireBackendEnv('smbclient');
		return NormalServer::class;
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
