<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

use Icewind\SMB\Exception\AuthenticationException;
use Icewind\SMB\Exception\InvalidHostException;

class Server {
	const CLIENT = 'smbclient';
	const LOCALE = 'en_US.UTF-8';

	/**
	 * @var string $host
	 */
	protected $host;

	/**
	 * @var string $user
	 */
	protected $user;

	/**
	 * @var string $password
	 */
	protected $password;

	/**
	 * Check if the smbclient php extension is available
	 *
	 * @return bool
	 */
	public static function NativeAvailable() {
		return function_exists('smbclient_state_new');
	}

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
	 * @return array
	 *
	 * @throws \Icewind\SMB\Exception\AuthenticationException
	 * @throws \Icewind\SMB\Exception\InvalidHostException
	 */
	private function listSMB() {
		$command = Server::CLIENT . ' --authentication-file=/proc/self/fd/3' .
			' -gL ' . escapeshellarg($this->getHost());
		$connection = new RawConnection($command);
		$connection->writeAuthentication($this->getUser(), $this->getPassword());
		$output = $connection->readAll();

		$line = $output[0];

		$line = rtrim($line, ')');
		if (substr($line, -23) === ErrorCodes::LogonFailure) {
			throw new AuthenticationException();
		}
		if (substr($line, -26) === ErrorCodes::BadHostName) {
			throw new InvalidHostException();
		}
		if (substr($line, -22) === ErrorCodes::Unsuccessful) {
			throw new InvalidHostException();
		}
		if (substr($line, -28) === ErrorCodes::ConnectionRefused) {
			throw new InvalidHostException();
		}

		return $output;
	}

	/**
	 * Filters the raw output
	 *
	 * @param  string $filter
	 * @return array
	 */
	private function filterRawOutput($rawOutput, $filter) {
		$filtered = array();

		foreach ($rawOutput as $line) {
			if (strpos($line, '|')) {
				list($type, $name, $description) = explode('|', $line);
				if (strtolower($type) === $filter) {
					$filtered[$name] = $description;
				}
			}
		}

		return $filtered;
	}

	/**
	 * @return \Icewind\SMB\IShare[]
	 *
	 */
	public function listShares() {
		$output = $this->listSMB();

		$shareNames = $this->filterRawOutput($output, 'disk');

		$shares = array();
		foreach ($shareNames as $name => $description) {
			$shares[] = $this->getShare($name);
		}
		return $shares;
	}

	/**
	 * @return array
	 *
	 */
	public function listPrinters() {
		$output = $this->listSMB();

		return $this->filterRawOutput($output, 'printer');
	}

	/**
	 * @param string $name
	 * @return \Icewind\SMB\IShare
	 */
	public function getShare($name) {
		return new Share($this, $name);
	}

	/**
	 * @return string
	 */
	public function getTimeZone() {
		$command = 'net time zone -S ' . escapeshellarg($this->getHost());
		return exec($command);
	}
}
