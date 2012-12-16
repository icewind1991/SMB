<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Get extends Command {
	public function run($arguments) {
		$path1 = $this->escapePath($arguments['path1']);
		$path2 = $this->escapeLocalPath($arguments['path2']); //second path is local, needs different escaping
		$output = $this->execute('get ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output);
	}

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
					throw new \SMB\NotFoundException();
				default:
					throw new \Exception();
			}
		}
	}
}
