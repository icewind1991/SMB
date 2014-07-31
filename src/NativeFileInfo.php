<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

class NativeFileInfo implements IFileInfo {
	const MODE_FILE = 0100000;

	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var \Icewind\SMB\NativeShare
	 */
	protected $share;

	/**
	 * @var array
	 */
	protected $statCache;

	/**
	 * @var int
	 */
	protected $modeCache;

	/**
	 * @param \Icewind\SMB\NativeShare $share
	 * @param string $path
	 * @param string $name
	 */
	public function __construct($share, $path, $name) {
		$this->share = $share;
		$this->path = $path;
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return array
	 */
	protected function stat() {
		if (!$this->statCache) {
			$this->statCache = $this->share->stat($this->getPath());
		}
		return $this->statCache;
	}

	/**
	 * @return int
	 */
	public function getSize() {
		$stat = $this->stat();
		return $stat['size'];
	}

	/**
	 * @return int
	 */
	public function getMTime() {
		$stat = $this->stat();
		return $stat['mtime'];
	}

	/**
	 * @return bool
	 */
	public function isDirectory() {
		$stat = $this->stat();
		return !($stat['mode'] & self::MODE_FILE);
	}

	/**
	 * @return int
	 */
	protected function getMode() {
		if (!$this->modeCache) {
			$this->modeCache = $this->share->getAttribute($this->path, 'system.dos_attr.mode');
		}
		return $this->modeCache;
	}

	/**
	 * @return bool
	 */
	public function isReadOnly() {
		$mode = $this->getMode();
		return (bool)($mode & FileInfo::MODE_READONLY);
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		$mode = $this->getMode();
		return (bool)($mode & FileInfo::MODE_HIDDEN);
	}
}
