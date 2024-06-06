<?php
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\AnonymousAuth;
use Icewind\SMB\Exception\DependencyException;
use Icewind\SMB\IAuth;
use Icewind\SMB\Native\NativeServer;
use Icewind\SMB\ServerFactory;
use Icewind\SMB\System;
use Icewind\SMB\Wrapped\Server;

class ServerFactoryTest extends TestCase {
	/** @var IAuth */
	private $credentials;

	protected function setUp(): void {
		parent::setUp();

		$this->credentials = new AnonymousAuth();
	}

	public function testSmbClient() {
		$this->requireBackendEnv('smbclient');
		$system = $this->getMockBuilder(System::class)
			->onlyMethods(['libSmbclientAvailable'])
			->getMock();
		$system->expects($this->any())
			->method('libSmbclientAvailable')
			->willReturn(false);
		$factory = new ServerFactory(null, $system);
		$this->assertInstanceOf(Server::class, $factory->createServer('localhost', $this->credentials));
	}

	public function testLibSmbClient() {
		$this->requireBackendEnv('libsmbclient');
		if (!function_exists('smbclient_state_new')) {
			$this->markTestSkipped('libsmbclient php extension not installed');
		}
		$factory = new ServerFactory();
		$this->assertInstanceOf(NativeServer::class, $factory->createServer('localhost', $this->credentials));
	}

	public function testNoBackend() {
		$this->expectException(DependencyException::class);
		$this->requireBackendEnv('smbclient');
		$system = $this->getMockBuilder(System::class)
			->setMethods(['libSmbclientAvailable', 'getSmbclientPath'])
			->getMock();
		$system->expects($this->any())
			->method('libSmbclientAvailable')
			->willReturn(false);
		$system->expects($this->any())
			->method('getSmbclientPath')
			->willReturn(null);
		$factory = new ServerFactory(null, $system);
		$factory->createServer('localhost', $this->credentials);
	}
}
