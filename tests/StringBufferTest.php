<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Robin Appelman <robin@icewind.nl>
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

use Icewind\SMB\StringBuffer;

class StringBufferTest extends TestCase {
	public function testPushRead() {
		$buffer = new StringBuffer();
		$this->assertEquals(0, $buffer->remaining());
		$buffer->push("foobar");
		$this->assertEquals(6, $buffer->remaining());
		$this->assertEquals("foo", $buffer->read(3));
		$this->assertEquals(3, $buffer->remaining());
		$this->assertEquals("b", $buffer->read(1));
		$this->assertEquals(2, $buffer->remaining());
		$this->assertEquals("ar", $buffer->read(10));
		$this->assertEquals(0, $buffer->remaining());
	}

	public function testReadEmpty() {
		$buffer = new StringBuffer();
		$this->assertEquals("", $buffer->read(10));
	}

	public function testAppend() {
		$buffer = new StringBuffer();
		$this->assertEquals(0, $buffer->remaining());
		$buffer->push("foo");
		$this->assertEquals(3, $buffer->remaining());
		$this->assertEquals("f", $buffer->read(1));
		$this->assertEquals(2, $buffer->remaining());
		$buffer->push("bar");
		$this->assertEquals(5, $buffer->remaining());
		$this->assertEquals("oobar", $buffer->read(10));
	}

	public function testFlush() {
		$buffer = new StringBuffer();
		$this->assertEquals(0, $buffer->remaining());
		$buffer->push("foobar");
		$this->assertEquals("f", $buffer->read(1));
		$this->assertEquals(5, $buffer->remaining());
		$this->assertEquals("oobar", $buffer->flush());

		$buffer->push("foobar");
		$this->assertEquals("foobar", $buffer->flush());
	}
}
