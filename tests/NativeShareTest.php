<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\ACL;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\InvalidArgumentException;
use Icewind\SMB\IOptions;
use Icewind\SMB\Native\NativeServer;
use Icewind\SMB\Options;
use Icewind\SMB\System;
use Icewind\SMB\TimeZoneProvider;

class NativeShareTest extends AbstractShareTest {
	public function getServerClass(): string {
		$this->requireBackendEnv('libsmbclient');
		if (!function_exists('smbclient_state_new')) {
			$this->markTestSkipped('libsmbclient php extension not installed');
		}
		return NativeServer::class;
	}

	public function testProtocolMatch() {
		$options = new Options();
		$options->setMinProtocol(IOptions::PROTOCOL_SMB2);
		$options->setMaxProtocol(IOptions::PROTOCOL_SMB3);
		$server = new NativeServer(
			$this->config->host,
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			new System(),
			new TimeZoneProvider(new System()),
			$options
		);
		$server->listShares();
		$this->assertTrue(true);
	}

	public function testToLowMaxProtocol() {
		$this->expectException(InvalidArgumentException::class);
		$options = new Options();
		$options->setMaxProtocol(IOptions::PROTOCOL_NT1);
		$server = new NativeServer(
			$this->config->host,
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			new System(),
			new TimeZoneProvider(new System()),
			$options
		);
		$server->listShares();
	}
}
