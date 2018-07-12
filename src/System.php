<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

use Icewind\SMB\Exception\Exception;

class System implements ISystem {
	private $smbclient;

	private $net;

	private $stdbuf;

	/**
	 * Get the path to a file descriptor of the current process
	 *
	 * @param int $num the file descriptor id
	 * @return string
	 * @throws Exception
	 */
	public function getFD($num) {
		$folders = [
			'/proc/self/fd',
			'/dev/fd'
		];
		foreach ($folders as $folder) {
			if (file_exists($folder)) {
				return $folder . '/' . $num;
			}
		}
		throw new Exception('Cant find file descriptor path');
	}

	public function getSmbclientPath() {
		if (!$this->smbclient) {
			$this->smbclient = trim(`which smbclient`);
		}
		return $this->smbclient;
	}

	public function getNetPath() {
		if (!$this->net) {
			$this->net = trim(`which net`);
		}
		return $this->net;
	}

	public function hasStdBuf() {
		if (!$this->stdbuf) {
			$result = null;
			$output = array();
			exec('which stdbuf 2>&1', $output, $result);
			$this->stdbuf = $result === 0;
		}
		return $this->stdbuf;
	}
}
