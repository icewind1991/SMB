<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class ListShares extends Command {
	public function run($arguments) {
		$auth = $this->escape($this->connection->getAuthString());
		$command = self::CLIENT . ' -N -U ' . $auth . ' ' . '-gL ' . $this->escape($this->connection->getHost()) . ' 2> /dev/null';
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
