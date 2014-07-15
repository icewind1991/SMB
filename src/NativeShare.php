<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Icewind\SMB;

require_once 'ErrorCodes.php';

class NativeShare implements IShare {
	/**
	 * @var Server $server
	 */
	private $server;

	/**
	 * @var string $name
	 */
	private $name;

	/**
	 * @var resource $state
	 */
	private $state;

	/**
	 * @var bool
	 */
	private static $registed = false;

	/**
	 * @param Server $server
	 * @param string $name
	 * @throws ConnectionError
	 */
	public function __construct($server, $name) {
		$this->server = $server;
		$this->name = $name;
		self::registerHandlers();
	}

	private static function registerHandlers() {
		set_error_handler(array('Icewind\SMB\NativeShare', 'errorHandler'));
		if (self::$registed) {
			return;
		}
		self::$registed = true;
		stream_wrapper_register('nativesmb', '\Icewind\SMB\NativeStream');
	}

	/**
	 * @throws ConnectionError
	 */
	public function connect() {
		if ($this->state and is_resource($this->state)) {
			return;
		}

		$user = $this->server->getUser();
		$workgroup = null;
		if (strpos($user, '/')) {
			list($workgroup, $user) = explode($user, '/');
		}
		$this->state = smbclient_state_new();
		$result = smbclient_state_init($this->state, $workgroup, $user, $this->server->getPassword());
		if (!$result) {
			throw new ConnectionError();
		}
	}

	/**
	 * Get the name of the share
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	private function buildUrl($path) {
		$url = 'smb://' . $this->server->getHost() . '/' . $this->name;
		if ($path) {
			$path = trim($path, '/');
			$url .= '/' . $path;
		}
		return $url;
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline, array $errcontext) {
		if (strpos($errstr, 'Path does not exist') or strpos($errstr, 'path doesn\'t exist')) {
			throw new NotFoundException($errstr);
		} else if (strpos($errstr, 'already exists')) {
			throw new AlreadyExistsException($errstr);
		} else if (strpos($errstr, 'Can\'t write to a directory') or
			strpos($errstr, 'use rmdir instead') or
			$errno = 20 // 20: ENOTDIR
		) {
			throw new InvalidTypeException($errstr);
		} else if (strpos($errstr, 'Workgroup not found') or
			strpos($errstr, 'Workgroup or server not found')
		) {
			throw new InvalidHostException($errstr);
		} else if (strpos($errstr, 'Permission denied')) {
			throw new AccessDeniedException($errstr);
		} else {
			throw new \Exception($errstr);
		}
	}

	/**
	 * List the content of a remote folder
	 *
	 * Returns a nested array in the format of
	 * [
	 *    $name => [
	 *        'size' => $size,
	 *        'type' => $type,
	 *        'time' => $mtime
	 *    ],
	 *    ...
	 * ]
	 *
	 * @param $path
	 * @return array[]
	 */
	public function dir($path) {
		$this->connect();
		$files = array();

		$dh = smbclient_opendir($this->state, $this->buildUrl($path));
		while ($file = smbclient_readdir($this->state, $dh)) {
			$name = $file['name'];
			if ($name !== '.' and $name !== '..') {
				$stat = $this->stat($path . '/' . $name);
				$files[$name] = array(
					'type' => ($file['type'] === 'directory') ? 'dir' : 'file',
					'size' => $stat['size'],
					'time' => $stat['mtime']
				);
			}
		}
		smbclient_closedir($this->state, $dh);
		return $files;
	}

	protected function stat($path) {
		$this->connect();
		return smbclient_stat($this->state, $this->buildUrl($path));
	}

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		$this->connect();
		return smbclient_mkdir($this->state, $this->buildUrl($path));
	}

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		$this->connect();
		return smbclient_rmdir($this->state, $this->buildUrl($path));
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function del($path) {
		$this->connect();
		return smbclient_unlink($this->state, $this->buildUrl($path));
	}

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function rename($from, $to) {
		$this->connect();
		return smbclient_rename($this->state, $this->buildUrl($from), $this->state, $this->buildUrl($to));
	}

	/**
	 * @param string $path
	 * @param string $mode
	 * @return resource
	 */
	protected function fopen($path, $mode) {
		$this->connect();
		return smbclient_open($this->state, $this->buildUrl($path), $mode);
	}

	/**
	 * @param string $path
	 * @return resource
	 */
	protected function create($path) {
		$this->connect();
		return smbclient_creat($this->state, $this->buildUrl($path));
	}

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 */
	public function put($source, $target) {
		$sourceHandle = fopen($source, 'rb');
		$targetHandle = $this->create($target);

		while ($data = fread($sourceHandle, 4096)) {
			smbclient_write($this->state, $targetHandle, $data);
		}
		return smbclient_close($this->state, $targetHandle);
	}

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 */
	public function get($source, $target) {
		$sourceHandle = $this->fopen($source, 'r');
		$targetHandle = fopen($target, 'wb');

		while ($data = smbclient_read($this->state, $sourceHandle, 4096)) {
			fwrite($targetHandle, $data);
		}
		return smbclient_close($this->state, $sourceHandle);
	}

	/**
	 * Open a readable stream top a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 */
	public function read($source) {
		$handle = $this->fopen($source, 'r');
		$context = stream_context_create(array(
			'nativesmb' => array(
				'state' => $this->state,
				'handle' => $handle
			)
		));
		return fopen('nativesmb://dummy', 'r', false, $context);
	}

	/**
	 * @return Server
	 */
	public function getServer() {
		return $this->server;
	}

	public function __destruct() {
		if ($this->state and is_resource($this->state)) {
			smbclient_state_free($this->state);
		}
		unset($this->state);
	}
}
