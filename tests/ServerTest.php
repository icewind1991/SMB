<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\AuthenticationException;
use Icewind\SMB\Exception\ConnectionRefusedException;
use Icewind\SMB\Exception\InvalidHostException;
use Icewind\SMB\IOptions;
use Icewind\SMB\IShare;
use Icewind\SMB\Options;
use Icewind\SMB\System;
use Icewind\SMB\TimeZoneProvider;
use Icewind\SMB\Wrapped\Server;

class ServerTest extends TestCase {
	/**
	 * @var \Icewind\SMB\Wrapped\Server $server
	 */
	private $server;

	private $config;

	public function setUp(): void {
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new Server(
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
	}

	public function testListShares() {
		$shares = $this->server->listShares();
		$names = array_map(function (IShare $share) {
			return $share->getName();
		}, $shares);

		$this->assertContains($this->config->share, $names);
	}

	public function testWrongPassword() {
		$this->expectException(AuthenticationException::class);
		$server = new Server(
			$this->config->host,
			new BasicAuth(
				$this->config->user,
				'test',
				uniqid()
			),
			new System(),
			new TimeZoneProvider(new System()),
			new Options()
		);
		$server->listShares();
	}

	public function testWrongHost() {
		$server = new Server(
			uniqid(),
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			new System(),
			new TimeZoneProvider(new System()),
			new Options()
		);
		try {
			$server->listShares();
			$this->fail("Expected exception");
		} catch (ConnectionRefusedException $e) {
			$this->assertTrue(true);
		} catch (InvalidHostException $e) {
			$this->assertTrue(true);
		}
	}

	public function testHostEscape() {
		$server = new Server(
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
		try {
			$server->listShares();
			$this->fail("Expected exception");
		} catch (ConnectionRefusedException $e) {
			$this->assertTrue(true);
		} catch (InvalidHostException $e) {
			$this->assertTrue(true);
		}
	}

	public function testProtocolMatch() {
		$options = new Options();
		$options->setMinProtocol(IOptions::PROTOCOL_SMB2);
		$options->setMaxProtocol(IOptions::PROTOCOL_SMB3);
		$server = new Server(
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
		$options = new Options();
		$options->setMaxProtocol(IOptions::PROTOCOL_NT1);
		$server = new Server(
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
		try {
			$server->listShares();
			$this->markTestSkipped("Server seems to accept NT1 connections");
		} catch (ConnectionRefusedException $e) {
			$this->assertTrue(true);
		}
	}
}
