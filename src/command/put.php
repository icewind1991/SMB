<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Put extends Command {
	public function run($arguments) {
		$path1 = $this->escapeLocalPath($arguments['path1']); //first path is local, needs different escaping
		$path2 = $this->escapePath($arguments['path2']);
		$output = $this->execute('put ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output);
	}

	/**
	 * @param $lines
	 *
	 * @throws \SMB\NotFoundException
	 * @return bool
	 */
	protected function parseOutput($lines) {
		if (count($lines) === 0) {
			return true;
		} else {
			if (strpos($lines[0], 'does not exist')) {
				throw new \SMB\NotFoundException();
			} else {
				parent::parseOutput($lines);
			}
			return false;
		}
	}
}
