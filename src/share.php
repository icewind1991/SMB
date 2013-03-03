<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
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
	 * @var string $name
	 */
	private $name;

	/**
	 * @var Connection $connection
	 */
	private $connection;

	/**
	 * @param Server $server
	 * @param string $share
	 */
	public function __construct($server, $name) {
		$this->server = $server;
		$this->name = $name;

		$command = Server::CLIENT . ' -U ' . escapeshellarg($this->server->getUser()) .
			' //' . $this->server->getHost() . '/' . $this->name;
		$this->connection = new Connection($command);
		$this->connection->write($this->server->getPassword());
	}

	public function getName() {
		return $this->name;
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
		$this->connection->write($input);
	}

	/**
	 * get all unprocessed output from smbclient
	 *
	 * @return array
	 */
	public function read() {
		return $this->connection->read();
	}

	/**
	 * @return Server
	 */
	public function getServer() {
		return $this->server;
	}
}
