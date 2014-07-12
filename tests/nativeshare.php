<?php

namespace SMB\Test;

require_once 'share.php';

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
		$this->server = new \Icewind\SMB\NativeServer($this->config->host, $this->config->user, $this->config->password);
		$this->share = new \Icewind\SMB\NativeShare($this->server, $this->config->share);
		if ($this->config->root) {
			$this->root = '/' . $this->config->root . '/' . uniqid();
		} else {
			$this->root = '/' . uniqid();
		}
		$this->share->mkdir($this->root);
	}
}
