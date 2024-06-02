<?php declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\ISystem;
use Icewind\SMB\TimeZoneProvider;

class TimeZoneProviderTest extends TestCase {
	/** @var ISystem|\PHPUnit_Framework_MockObject_MockObject */
	private $system;
	/** @var TimeZoneProvider */
	private $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->system = $this->createMock(ISystem::class);
		$this->provider = new TimeZoneProvider($this->system);
	}

	private function getDummyCommand($output) {
		return "echo '$output' || false";
	}

	public function testFQDN() {
		$this->system->method('getNetPath')
			->willReturn($this->getDummyCommand("+800"));
		$this->system->method('getDatePath')
			->willReturn($this->getDummyCommand("+700"));

		$this->assertEquals('+800', $this->provider->get('foo.bar.com'));
	}

	public function testLocal() {
		$this->system->method('getNetPath')
			->willReturn($this->getDummyCommand("+800"));
		$this->system->method('getDatePath')
			->willReturn($this->getDummyCommand("+700"));

		$this->assertEquals('+700', $this->provider->get('foobar'));
	}

	public function testFQDNNoNet() {
		$this->system->method('getNetPath')
			->willReturn(null);
		$this->system->method('getDatePath')
			->willReturn($this->getDummyCommand("+700"));

		$this->assertEquals('+700', $this->provider->get('foo.bar.com'));
	}

	public function testNoNetNoDate() {
		$this->system->method('getNetPath')
			->willReturn(null);
		$this->system->method('getDatePath')
			->willReturn(null);

		$this->assertEquals(date_default_timezone_get(), $this->provider->get('foo.bar.com'));
	}
}
