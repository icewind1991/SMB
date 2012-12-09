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
	 * @var Connection $connection
	 */
	private $connection;

	/**
	 * @var string $name
	 */
	private $name;

	/**
	 * @param Connection $connection
	 * @param string $share
	 */
	public function __construct($connection, $name) {
		$this->connection = $connection;
		$this->name = $name;
	}

	/**
	 * List the content of a remote folder
	 *
	 * @param $path
	 * @return array
	 */
	public function dir($path) {
		return (new Command\Dir($this->connection))->run(array('path' => $path, 'share' => $this->name));
	}

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		return (new Command\Mkdir($this->connection))->run(array('path' => $path, 'share' => $this->name));
	}

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		return (new Command\Rmdir($this->connection))->run(array('path' => $path, 'share' => $this->name));
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function del($path) {
		return (new Command\Del($this->connection))->run(array('path' => $path, 'share' => $this->name));
	}

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function rename($from, $to) {
		return (new Command\Rename($this->connection))->run(array('path1' => $from, 'path2' => $to, 'share' => $this->name));
	}

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 */
	public function put($source, $target) {
		return (new Command\Put($this->connection))->run(array('path1' => $source, 'path2' => $target, 'share' => $this->name));
	}

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 */
	public function get($source, $target) {
		return (new Command\Get($this->connection))->run(array('path1' => $source, 'path2' => $target, 'share' => $this->name));
	}
}
