<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Icewind\SMB;

class NativeStream {
	public $context;

	private $state;

	private $handle;

	public function stream_close() {
		return smbclient_close($this->state, $this->handle);
	}

	public function stream_eof() {
	}

	public function stream_flush() {
	}


	public function stream_open($path, $mode, $options, &$opened_path) {
		$context = stream_context_get_options($this->context);
		if (isset($context['nativesmb'])) {
			$context = $context['nativesmb'];
		} else {
			throw new Exception('Invalid context');
		}
		if (isset($context['state']) and isset($context['handle'])) {
			$this->state = $context['state'];
			$this->handle = $context['handle'];
			return true;
		} else {
			throw new Exception('Invalid context');
		}
	}

	public function stream_read($count) {
		return smbclient_read($this->state, $this->handle, $count);
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		return smbclient_lseek($this->state, $this->handle, $offset, $whence);
	}

	public function stream_stat() {
		return smbclient_fstat($this->state, $this->handle);
	}

	public function stream_tell() {
		return $this->stream_seek(0, SEEK_CUR);
	}

	public function stream_write($data) {
		return smbclient_write($this->state, $this->handle, $data);
	}
}
