<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB;

class Server {
	const CLIENT = 'smbclient';
	const LOCALE = 'en_US.UTF-8';

	const CACHING_ENABLED = true;
	const CACHING_DISABLED = false;

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
	 * @var bool $caching
	 */
	private $caching;

	/**
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param bool $caching
	 */
	public function __construct($host, $user, $password, $caching = self::CACHING_DISABLED) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->caching = $caching;
	}

	/**
	 * @return string
	 */
	public function getAuthString() {
		return $this->user . '%' . $this->password;
	}

	/**
	 * @return string
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * @return Share[]
	 * @throws AuthenticationException
	 * @throws InvalidHostException
	 */
	public function listShares() {
		$auth = escapeshellarg($this->getAuthString()); //TODO: don't pass password as shell argument
		$command = self::CLIENT . ' -N -U ' . $auth . ' ' . '-gL ' . escapeshellarg($this->getHost()); // . ' 2> /dev/null';
		exec($command, $output);

		$line = $output[0];
		$line = rtrim($line, ')');
		if (substr($line, -23) === 'NT_STATUS_LOGON_FAILURE') {
			throw new AuthenticationException();
		}
		if (substr($line, -26) === 'NT_STATUS_BAD_NETWORK_NAME') {
			throw new InvalidHostException();
		}
		if (substr($line, -22) === 'NT_STATUS_UNSUCCESSFUL') {
			throw new InvalidHostException();
		}
		if (substr($line, -28) === 'NT_STATUS_CONNECTION_REFUSED') {
			throw new InvalidHostException();
		}

		$shareNames = array();
		foreach ($output as $line) {
			if (strpos($line, '|')) {
				list($type, $name, $description) = explode('|', $line);
				if (strtolower($type) === 'disk') {
					$shareNames[$name] = $description;
				}
			}
		}

		$shares = array();
		foreach ($shareNames as $name => $description) {
			$shares[] = $this->getShare($name);
		}
		return $shares;
	}

	/**
	 * @param string $name
	 * @return Share
	 */
	public function getShare($name) {
		if ($this->caching === self::CACHING_ENABLED) {
			return new CachingShare($this, $name);
		} else {
			return new Share($this, $name);
		}
	}
}
