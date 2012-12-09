<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Rename extends Double {
	public function __construct($connection) {
		parent::__construct($connection);
		$this->command = 'rename';
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
