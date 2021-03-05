<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Native;

use Icewind\SMB\StringBuffer;

/**
 * Stream optimized for write only usage
 */
class NativeWriteStream extends NativeStream {
	const CHUNK_SIZE = 1048576; // 1MB chunks

	/** @var StringBuffer */
	private $writeBuffer;

	private $pos = 0;

	public function stream_open($path, $mode, $options, &$opened_path) {
		$this->writeBuffer = new StringBuffer();

		return parent::stream_open($path, $mode, $options, $opened_path);
	}

	/**
	 * Wrap a stream from libsmbclient-php into a regular php stream
	 *
	 * @param NativeState $state
	 * @param resource $smbStream
	 * @param string $mode
	 * @param string $url
	 * @return resource
	 */
	public static function wrap($state, $smbStream, $mode, $url) {
		stream_wrapper_register('nativesmb', NativeWriteStream::class);
		$context = stream_context_create([
			'nativesmb' => [
				'state'  => $state,
				'handle' => $smbStream,
				'url'    => $url
			]
		]);
		$fh = fopen('nativesmb://', $mode, false, $context);
		stream_wrapper_unregister('nativesmb');
		return $fh;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		$this->flushWrite();
		$result = parent::stream_seek($offset, $whence);
		if ($result) {
			$this->pos = parent::stream_tell();
		}
		return $result;
	}

	private function flushWrite() {
		$this->state->write($this->handle, $this->writeBuffer->flush(), $this->url);
	}

	public function stream_write($data) {
		$written = $this->writeBuffer->push($data);
		$this->pos += $written;

		if ($this->writeBuffer->remaining() >= self::CHUNK_SIZE) {
			$this->flushWrite();
		}

		return $written;
	}

	public function stream_close() {
		try {
			$this->flushWrite();
			$flushResult = true;
		} catch (\Exception $e) {
			$flushResult = false;
		}
		return parent::stream_close() && $flushResult;
	}

	public function stream_tell() {
		return $this->pos;
	}

	public function stream_read($count) {
		return false;
	}

	public function stream_truncate($size) {
		$this->flushWrite();
		$this->pos = $size;
		return parent::stream_truncate($size);
	}
}
