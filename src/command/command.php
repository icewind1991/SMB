<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

abstract class Command {
	const CLIENT = 'smbclient';

	/**
	 * @var \SMB\Connection $connection
	 */
	protected $connection;

	/**
	 * @param \SMB\Connection $connection
	 */
	public function __construct($connection) {
		$this->connection = $connection;
	}

	/**
	 * @param string $command
	 * @return array
	 */
	protected function execute($command) {
		$auth = $this->escape($this->connection->getAuthString());
		$command = self::CLIENT . ' -N -U ' . $auth . ' ' . $command . ' 2> /dev/null';
		exec($command, $output);
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
		return '"' . trim(escapeshellarg($path), "'") . '"';
	}
}
