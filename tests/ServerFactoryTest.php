<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
