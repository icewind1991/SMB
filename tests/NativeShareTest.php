<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\Native\NativeServer;
use Icewind\SMB\Options;
use Icewind\SMB\System;
use Icewind\SMB\TimeZoneProvider;

class NativeShareTest extends AbstractShareTest {
    public function setUp() {
		$this->requireBackendEnv('libsmbclient');
		if (!function_exists('smbclient_state_new')) {
			$this->markTestSkipped('libsmbclient php extension not installed');
		}
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new NativeServer(
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
        $fh = $this->share->append($this->root . '/' . $name);
        fwrite($fh, 'foo');
        fclose($fh);

        $fh = $this->share->append($this->root . '/' . $name);
        fwrite($fh, 'bar');
        fclose($fh);

        $tmpFile1 = tempnam('/tmp', 'smb_test_');
        $this->assertEquals('foobar', file_get_contents($tmpFile1));
        $this->share->del($this->root . '/' . $name);
        unlink($tmpFile1);
    }
}
