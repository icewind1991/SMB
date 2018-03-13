<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Wrapped;

use Icewind\SMB\AbstractServer;
use Icewind\SMB\System;

class Server extends AbstractServer {
	/**
	 * Check if the smbclient php extension is available
	 *
	 * @return bool
	 */
	public static function available(System $system) {
		return $system->getSmbclientPath();
	}

	private function getAuthFileArgument() {
		if ($this->getAuth()->getUsername()) {
			return '--authentication-file=' . System::getFD(3);
		} else {
			return '';
		}
	}

	/**
	 * @return \Icewind\SMB\IShare[]
	 *
	 * @throws \Icewind\SMB\Exception\AuthenticationException
	 * @throws \Icewind\SMB\Exception\InvalidHostException
	 */
	public function listShares() {
		$command = sprintf('%s %s %s -L %s',
			$this->system->getSmbclientPath(),
			$this->getAuthFileArgument(),
			$this->getAuth()->getExtraCommandLineArguments(),
			escapeshellarg('//' . $this->getHost())
		);
		$connection = new RawConnection($command);
		$connection->writeAuthentication($this->getAuth()->getUsername(), $this->getAuth()->getPassword());
		$connection->connect();
		$output = $connection->readAll();
		$parser = new Parser($this->timezoneProvider);

		if (isset($output[0])) {
			$parser->checkConnectionError($output[0]);
		}

		$shareNames = $parser->parseListShares($output);

		$shares = array();
		foreach ($shareNames as $name => $description) {
			$shares[] = $this->getShare($name);
		}
		return $shares;
	}

	/**
	 * @param string $name
	 * @return \Icewind\SMB\IShare
	 */
	public function getShare($name) {
		return new Share($this, $name, $this->system);
	}
}
