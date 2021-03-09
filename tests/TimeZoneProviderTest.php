<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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
