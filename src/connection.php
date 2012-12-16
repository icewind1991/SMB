<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB;

class Connection {
	const CLIENT = 'smbclient';

	/**
	 * @var string $host
	 */
	private $host;

	/**
	 * @var string $user
	 */
	private $user;

	/**
	 * @var string $password
	 */
	private $password;

	/**
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 */
	public function __construct($host, $user, $password) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
	}

	/**
	 * @return string
	 */
	public function getAuthString() {
		return $this->user . '%' . $this->password;
	}

	/**
	 * return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * @return Share[]
	 */
	public function listShares() {
		$cmd = new Command\ListShares($this);
		$shareNames = $cmd->run(null);
		$shares = array();
		foreach ($shareNames as $name => $description) {
			$shares[] = new Share($this, $name);
		}
		return $shares;
	}

	/**
	 * @param string $name
	 * @return Share
	 */
	public function getShare($name) {
		return new Share($this, $name);
	}
}
