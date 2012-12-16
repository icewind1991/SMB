<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

/**
 * run a command with one path parameter
 */
abstract class Simple extends Command {
	/**
	 * @var string $command
	 */
	protected $command;

	public function run($arguments) {
		$path = $this->escapePath($arguments['path']);
		$cmd = $this->command . ' ' . $path;
		$output = $this->execute($cmd);
		return $this->parseOutput($output);
	}
}
