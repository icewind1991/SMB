<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

abstract class Command {
	/**
	 * @var \SMB\Share $connection
	 */
	protected $share;

	/**
	 * @param \SMB\Share $connection
	 */
	public function __construct($share) {
		$this->share = $share;
	}

	/**
	 * @param string $command
	 * @return array
	 */
	protected function execute($command) {
		$this->share->write($command . PHP_EOL);
		$output = $this->share->read();
		return $output;
	}

	abstract public function run($arguments);

	/**
	 * @param array $lines
	 * @return mixed
	 */
	abstract protected function parseOutput($lines);

	/**
	 * @param string $string
	 * @return string
	 */
	public function escape($string) {
		return escapeshellarg($string);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public function escapePath($path) {
		$path = str_replace('/', '\\', $path);
		$path = str_replace('"', '^"', $path);
		return '"' . $path . '"';
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public function escapeLocalPath($path) {
		$path = str_replace('"', '\"', $path);
		return '"' . $path . '"';
	}
}
