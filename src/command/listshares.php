<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class ListShares {

	/**
	 * @var \SMB\Server $server
	 */
	private $server;

	/**
	 * @param \SMB\Server $server
	 */
	public function __construct($server){
		$this->server=$server;
	}

	public function run() {
		$auth = escapeshellarg($this->server->getAuthString()); //TODO: don't pass password as shell argument
		$command = \SMB\Server::CLIENT . ' -N -U ' . $auth . ' ' . '-gL ' . escapeshellarg($this->server->getHost()) . ' 2> /dev/null';
		exec($command, $output);
		return $this->parseOutput($output);
	}

	/**
	 * @param $lines
	 * @return array
	 */
	protected function parseOutput($lines) {
		$shares = array();
		foreach ($lines as $line) {
			if (strpos($line, '|')) {
				list($type, $name, $description) = explode('|', $line);
				if (strtolower($type) === 'disk') {
					$shares[$name] = $description;
				}
			}
		}
		return $shares;
	}
}
