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
	 * @var \SMB\Share $share
	 */
	protected $share;

	/**
	 * @param \SMB\Share $share
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
	 * @param $lines
	 * @return bool
	 */
	protected function parseOutput($lines) {
		if (count($lines) === 0) {
			return true;
		} else {
			list($error,) = explode(' ', $lines[0]);
			switch ($error) {
				case 'NT_STATUS_OBJECT_PATH_NOT_FOUND':
				case 'NT_STATUS_OBJECT_NAME_NOT_FOUND':
				case 'NT_STATUS_NO_SUCH_FILE':
					throw new \SMB\NotFoundException();
				case 'NT_STATUS_OBJECT_NAME_COLLISION':
					throw new \SMB\AlreadyExistsException();
				case 'NT_STATUS_ACCESS_DENIED':
					throw new \SMB\AccessDeniedException();
				case 'NT_STATUS_DIRECTORY_NOT_EMPTY':
					throw new \SMB\NotEmptyException();
				default:
					throw new \Exception();
			}
		}
	}

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
