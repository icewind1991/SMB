<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
