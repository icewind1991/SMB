<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Icewind\SMB;

class NativeServer extends Server {
	/**
	 * @var resource
	 */
	protected $state;

	protected function connect() {
		if ($this->state and is_resource($this->state)) {
			return;
		}
		set_error_handler(array('Icewind\SMB\NativeShare', 'errorHandler'));
		$user = $this->getUser();
		$workgroup = null;
		if (strpos($user, '/')) {
			list($workgroup, $user) = explode($user, '/');
		}
		$this->state = smbclient_state_new();
		$result = smbclient_state_init($this->state, $workgroup, $this->getPassword(), $user);
		if (!$result) {
			throw new ConnectionError();
		}
	}

	/**
	 * @return \Icewind\SMB\IShare[]
	 * @throws \Icewind\SMB\AuthenticationException
	 * @throws \Icewind\SMB\InvalidHostException
	 */
	public function listShares() {
		$this->connect();
		$shares = array();
		$dh = smbclient_opendir($this->state, 'smb://' . $this->getHost());
		while ($share = smbclient_readdir($this->state, $dh)) {
			if ($share['type'] === 'file share') {
				$shares[] = $this->getShare($share['name']);
			}
		}
		smbclient_closedir($this->state, $dh);
		return $shares;
	}

	/**
	 * @param string $name
	 * @return \Icewind\SMB\IShare
	 */
	public function getShare($name) {
		return new NativeShare($this, $name);
	}

	public function __destruct() {
		if ($this->state and is_resource($this->state)) {
			smbclient_state_free($this->state);
		}
		unset($this->state);
	}
}
