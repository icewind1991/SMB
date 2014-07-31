<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
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
	 * @param Server $server
	 * @param string $name
	 * @throws ConnectionError
	 */
	public function __construct($server, $name) {
		$this->server = $server;
		$this->name = $name;
	}

	public static function registerErrorHandler() {
		set_error_handler(array('Icewind\SMB\NativeShare', 'errorHandler'));
	}

	public static function restoreErrorHandler() {
		restore_error_handler();
	}

	/**
	 * @throws \Icewind\SMB\ConnectionError
	 * @throws \Icewind\SMB\AuthenticationException
	 * @throws \Icewind\SMB\InvalidHostException
	 */
	protected function connect() {
		if ($this->state and is_resource($this->state)) {
			return;
		}

		$user = $this->server->getUser();
		$workgroup = null;
		if (strpos($user, '/')) {
			list($workgroup, $user) = explode($user, '/');
		}
		self::registerErrorHandler();
		$this->state = smbclient_state_new();
		smbclient_state_init($this->state, $workgroup, $user, $this->server->getPassword());
		self::restoreErrorHandler();
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

	public static function errorHandler($errno, $errorString) {
		if (strpos($errorString, 'Path does not exist') or strpos($errorString, 'path doesn\'t exist')) {
			throw new NotFoundException($errorString);
		} else if (strpos($errorString, 'already exists')) {
			throw new AlreadyExistsException($errorString);
		} else if (strpos($errorString, 'Can\'t write to a directory') or
			strpos($errorString, 'use rmdir instead') or
			strpos($errorString, 'unknown error (20)') // 20: ENOTDIR
		) {
			throw new InvalidTypeException($errorString);
		} else if (strpos($errorString, 'Workgroup not found') or
			strpos($errorString, 'Workgroup or server not found')
		) {
			throw new InvalidHostException($errorString);
		} else if (strpos($errorString, 'Permission denied')) {
			throw new AccessDeniedException($errorString);
		} else if (strpos($errorString, 'unknown error (110)') or
			strpos($errorString, 'unknown error (111)') or
			strpos($errorString, 'unknown error (112)') or
			strpos($errorString, 'unknown error (113)')
		) {
			// errors for connection timeout, connection refused, host is down and
			// no route to host, respectively
			throw new ConnectionError($errorString);
		} else {
			throw new \Exception($errorString);
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
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function dir($path) {
		$this->connect();
		$files = array();

		self::registerErrorHandler();
		$dh = smbclient_opendir($this->state, $this->buildUrl($path));
		while ($file = smbclient_readdir($this->state, $dh)) {
			$name = $file['name'];
			if ($name !== '.' and $name !== '..') {
				$files [] = new NativeFileInfo($this, $path . '/' . $name, $name);
			}
		}
		smbclient_closedir($this->state, $dh);
		self::restoreErrorHandler();
		return $files;
	}

	public function stat($path) {
		$this->connect();
		self::registerErrorHandler();
		$stat = smbclient_stat($this->state, $this->buildUrl($path));
		self::restoreErrorHandler();
		return $stat;
	}

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\AlreadyExistsException
	 */
	public function mkdir($path) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_mkdir($this->state, $this->buildUrl($path));
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function rmdir($path) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_rmdir($this->state, $this->buildUrl($path));
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function del($path) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_unlink($this->state, $this->buildUrl($path));
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\AlreadyExistsException
	 */
	public function rename($from, $to) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_rename($this->state, $this->buildUrl($from), $this->state, $this->buildUrl($to));
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * @param string $path
	 * @param string $mode
	 * @return resource
	 */
	protected function fopen($path, $mode) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_open($this->state, $this->buildUrl($path), $mode);
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * @param string $path
	 * @return resource
	 */
	protected function create($path) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_creat($this->state, $this->buildUrl($path));
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function put($source, $target) {
		$sourceHandle = fopen($source, 'rb');
		$targetHandle = $this->create($target);

		self::registerErrorHandler();
		while ($data = fread($sourceHandle, 4096)) {
			smbclient_write($this->state, $targetHandle, $data);
		}
		smbclient_close($this->state, $targetHandle);
		restore_error_handler();
		return true;
	}

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function get($source, $target) {
		$sourceHandle = $this->fopen($source, 'r');
		$targetHandle = fopen($target, 'wb');

		self::registerErrorHandler();
		while ($data = smbclient_read($this->state, $sourceHandle, 4096)) {
			fwrite($targetHandle, $data);
		}
		smbclient_close($this->state, $sourceHandle);
		restore_error_handler();
	}

	/**
	 * Open a readable stream top a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function read($source) {
		$handle = $this->fopen($source, 'r');
		return NativeStream::wrap($this->state, $handle, 'r');
	}

	/**
	 * Open a readable stream top a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function write($source) {
		$handle = $this->create($source);
		return NativeStream::wrap($this->state, $handle, 'w');
	}

	/**
	 * List the available extended attributes for the path (returns a fixed list)
	 *
	 * @param string $path
	 * @return array list the available attributes for the path
	 */
	public function listAttributes($path) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_listxattr($this->state, $this->buildUrl($path));
		self::restoreErrorHandler();
		return $result;
	}

	/**
	 * Get extended attributes for the path
	 *
	 * @param string $path
	 * @param string $attribute attribute to get the info
	 * @return string the attribute value
	 */
	public function getAttribute($path, $attribute) {
		$this->connect();
		self::registerErrorHandler();
		$result = smbclient_getxattr($this->state, $this->buildUrl($path), $attribute);
		self::restoreErrorHandler();
		// parse hex string
		if ($attribute === 'system.dos_attr.mode') {
			$result = hexdec(substr($result, 2));
		}
		return $result;
	}
}
