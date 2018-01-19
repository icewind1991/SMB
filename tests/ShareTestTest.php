<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\Server as NormalServer;

class ShareTestTest extends AbstractShareTest {
	public function setUp() {
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NormalServer($this->config->host, $this->config->user, $this->config->password);
		$this->share = $this->server->getShare($this->config->share);
		if ($this->config->root) {
			$this->root = '/' . $this->config->root . '/' . uniqid();
		} else {
			$this->root = '/' . uniqid();
		}
		$this->share->mkdir($this->root);
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\ConnectException
	 */
	public function testHostEscape() {
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NormalServer($this->config->host . ';asd', $this->config->user, $this->config->password);
		$share = $this->server->getShare($this->config->share);
		$share->dir($this->root);
	}

	/**
	 * @expectedException \Icewind\SMB\Exception\DependencyException
	 */
	public function testNoSmbclient() {
		$system = $this->getMockBuilder('\Icewind\SMB\System')
			->setMethods(['getSmbclientPath'])
			->getMock();
		$share = new \Icewind\SMB\Share($this->server, 'dummy', $system);

		$system->expects($this->any())
			->method('getSmbclientPath')
			->will($this->returnValue(''));

		$share->mkdir('');
	}
}
