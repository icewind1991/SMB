<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB;

class Connection {
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
			1 => array("pipe", "w"),
		);
		putenv('LC_ALL=' . Server::LOCALE);
		setlocale(LC_ALL, Server::LOCALE);
		$this->process = proc_open($command, $descriptorSpec, $this->pipes, null, array(
			'CLI_FORCE_INTERACTIVE' => 'y' // Needed or the prompt isn't displayed!!
		));
		if (!is_resource($this->process)) {
			throw new ConnectionError();
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
	 * @return array
	 */
	public function read() {
		fgets($this->pipes[1]); //first line is prompt
		$output = array();
		$line = fgets($this->pipes[1]);
		while (substr($line, 0, 4) !== 'smb:') { //next prompt functions as delimiter
			$output[] .= $line;
			$line = fgets($this->pipes[1]);
		}
		return $output;
	}

	public function __destruct() {
		proc_close($this->process);
	}
}
