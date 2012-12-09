<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Put extends Command {
	public function __construct($connection) {
		parent::__construct($connection);
		$this->command = 'put';
	}

	public function run($arguments) {
		$path1 = $this->escape($arguments['path1']); //first path is local, needs different escaping
		$path2 = $this->escapePath($arguments['path2']);
		$share = $arguments['share'];
		$postFix = (isset($arguments['postfix'])) ? $arguments['postfix'] : '';
		$cmd = $this->escape('//' . $this->connection->getHost() . '/' . $share);
		$cmd .= " -c '" . $this->command . ' ' . $path1 . ' ' . $path2 . $postFix . "'";
		$output = $this->execute($cmd);
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
			if (strpos($lines[0], 'does not exist')) {
				throw new \SMB\NotFoundException();
			}
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
