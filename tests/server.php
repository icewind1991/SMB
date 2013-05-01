<?php

namespace SMB\Test;

class Server extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \SMB\Server $server
	 */
	private $server;

	private $config;

	public function setUp() {
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new \SMB\Server($this->config->host, $this->config->user, $this->config->password);
	}

	public function testListShares() {
		$shares = $this->server->listShares();
		foreach ($shares as $share) {
			if ($share->getName() === $this->config->share) {
				return;
			}
		}
		$this->fail('Share "' . $this->config->share . '" not found');
	}

	/**
	 * @expectedException \SMB\AuthenticationException
	 */
	public function testWrongUserName() {
		$server = new \SMB\Server($this->config->host, uniqid(), $this->config->password);
		$server->listShares();
	}
}
