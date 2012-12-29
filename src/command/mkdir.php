<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Mkdir extends Simple {
	public function __construct($connection) {
		parent::__construct($connection);
		$this->command = 'mkdir';
	}
}
