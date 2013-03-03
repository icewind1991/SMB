<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB;

class Share {
	/**
	 * @var Server $server
	 */
	private $server;

	/**
	 * @var resource $process
	 */
	private $process;

	/**
	 * @var resource[] $pipes
	 *
	 * $pipes[0] holds STDIN for smbclient
	 * $pipes[1] holds STDOUT for smbclient
	 */
	private $pipes;

	/**
	 * @var string $name
	 */
	private $name;

	/**
	 * @param Server $server
	 * @param string $share
	 */
	public function __construct($server, $name) {
		$this->server = $server;
		$this->name = $name;

		$descriptorSpec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
//			2 => array("file", "/tmp/smberror", "a")
		);

		putenv('LC_ALL=' . Server::LOCALE);
		setlocale(LC_ALL, Server::LOCALE);
		$command = Server::CLIENT . ' -N -U ' . $this->server->getAuthString() .
			' //' . $this->server->getHost() . '/' . $this->name;
		$this->process = proc_open($command, $descriptorSpec, $this->pipes, null, array(
			'CLI_FORCE_INTERACTIVE' => 'y' // Needed or the prompt isn't displayed!!
		));
		if (!is_resource($this->process)) {
			throw new ConnectionError();
		}
	}

	public function __destruct() {
		proc_close($this->process);
	}

	/**
	 * List the content of a remote folder
	 *
	 * @param $path
	 * @return array
	 */
	public function dir($path) {
		return (new Command\Dir($this))->run(array('path' => $path));
	}

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		return (new Command\Mkdir($this))->run(array('path' => $path));
	}

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		return (new Command\Rmdir($this))->run(array('path' => $path));
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function del($path) {
		return (new Command\Del($this))->run(array('path' => $path));
	}

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function rename($from, $to) {
		return (new Command\Rename($this))->run(array('path1' => $from, 'path2' => $to));
	}

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 */
	public function put($source, $target) {
		return (new Command\Put($this))->run(array('path1' => $source, 'path2' => $target));
	}

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 */
	public function get($source, $target) {
		return (new Command\Get($this))->run(array('path1' => $source, 'path2' => $target));
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

	/**
	 * @return Server
	 */
	public function getServer(){
		return $this->server;
	}
}
