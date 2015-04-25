<?php
/**
 * Copyright (c) 2015 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

abstract class TestCase extends \PHPUnit_Framework_TestCase {
	protected function requireBackendEnv($backend) {
		if (getenv('BACKEND') and getenv('BACKEND') !== $backend) {
			$this->markTestSkipped('Skipping tests for ' . $backend . ' backend');
		}
	}
}
