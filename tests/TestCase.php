<?php
/**
 * SPDX-FileCopyrightText: 2015 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: MIT
 */

namespace Icewind\SMB\Test;

abstract class TestCase extends \PHPUnit\Framework\TestCase {
	protected function requireBackendEnv($backend) {
		if (getenv('BACKEND') and getenv('BACKEND') !== $backend) {
			$this->markTestSkipped('Skipping tests for ' . $backend . ' backend');
		}
	}
}
