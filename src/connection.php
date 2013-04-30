<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB;

class Connection {

	const DELIMITER = 'smb:';

	/**
	 * @var resource[] $pipes
	 *
	 * $pipes[0] holds STDIN for smbclient
	 * $pipes[1] holds STDOUT for smbclient
	 */
	private $pipes;

	/**
	 * @var resource $process
	 */
	private $process;


	public function __construct($command) {
		$descriptorSpec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w")
		);
		setlocale(LC_ALL, Server::LOCALE);
		$this->process = proc_open($command, $descriptorSpec, $this->pipes, null, array(
			'CLI_FORCE_INTERACTIVE' => 'y', // Needed or the prompt isn't displayed!!
			'LC_ALL' => Server::LOCALE
		));
		if (!$this->isValid()) {
			throw new ConnectionError();
		}
	}

	/**
	 * check if the connection is still active
	 *
	 * @return bool
	 */
	public function isValid() {
		if (is_resource($this->process)) {
			$status = proc_get_status($this->process);
			return $status['running'];
		} else {
			return false;
		}
	}

	/**
	 * send input to smbclient
	 *
	 * @param string $input
	 */
	public function write($input) {
		fwrite($this->pipes[0], $input);
		fwrite($this->pipes[0], PHP_EOL); //make sure we have a recognizable delimiter
		fflush($this->pipes[0]);
	}

	/**
	 * get all unprocessed output from smbclient
	 *
	 * @throws ConnectionError
	 * @return array
	 */
	public function read() {
		if (!$this->isValid()) {
			throw new ConnectionError();
		}
		$line = trim(fgets($this->pipes[1])); //first line is prompt
		$this->checkConnectionError($line);

		$output = array();
		$line = fgets($this->pipes[1]);
		$length = strlen(self::DELIMITER);
		while (substr($line, 0, $length) !== self::DELIMITER) { //next prompt functions as delimiter
			$output[] .= $line;
			$line = fgets($this->pipes[1]);
		}
		return $output;
	}

	/**
	 * check if the first line holds a connection failure
	 *
	 * @param $line
	 * @throws AuthenticationException
	 * @throws InvalidHostException
	 */
	private function checkConnectionError($line) {
		$line = rtrim($line, ')');
		$authError = 'NT_STATUS_LOGON_FAILURE';
		if (substr($line, -23) === $authError) {
			$this->pipes = array(null, null);
			throw new AuthenticationException();
		}
		$addressError = 'NT_STATUS_BAD_NETWORK_NAME';
		if (substr($line, -26) === $addressError) {
			$this->pipes = array(null, null);
			throw new InvalidHostException();
		}
	}

	public function close() {
		$this->write('close' . PHP_EOL);
	}

	public function __destruct() {
		$this->close();
		proc_terminate($this->process);
		proc_close($this->process);
	}
}
