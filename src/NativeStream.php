<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

class NativeStream {
	/**
	 * @var resource
	 */
	public $context;

	/**
	 * @var \Icewind\SMB\NativeState
	 */
	private $state;

	/**
	 * @var resource
	 */
	private $handle;

	/**
	 * Wrap a stream from libsmbclient-php into a regular php stream
	 *
	 * @param \Icewind\SMB\NativeState $state
	 * @param resource $smbStream
	 * @param string $mode
	 * @return resource
	 */
	public static function wrap($state, $smbStream, $mode) {
		stream_wrapper_register('nativesmb', '\Icewind\SMB\NativeStream');
		$context = stream_context_create(array(
			'nativesmb' => array(
				'state' => $state,
				'handle' => $smbStream
			)
		));
		$fh = fopen('nativesmb://', $mode, false, $context);
		stream_wrapper_unregister('nativesmb');
		return $fh;
	}

	public function stream_close() {
		return $this->state->close($this->handle);
	}

	public function stream_eof() {
	}

	public function stream_flush() {
	}


	public function stream_open() {
		$context = stream_context_get_options($this->context);
		$this->state = $context['nativesmb']['state'];
		$this->handle = $context['nativesmb']['handle'];
		return true;
	}

	public function stream_read($count) {
		return $this->state->read($this->handle, $count);
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		return $this->state->lseek($this->handle, $offset, $whence);
	}

	public function stream_stat() {
		return $this->state->fstat($this->handle);
	}

	public function stream_tell() {
		return $this->stream_seek(0, SEEK_CUR);
	}

	public function stream_write($data) {
		return $this->state->write($this->handle, $data);
	}
}
