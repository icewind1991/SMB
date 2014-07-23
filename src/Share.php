<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Icewind\SMB;

use Icewind\Streams\CallbackWrapper;

class Share implements IShare {
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
	public $connection;

	private $serverTimezone;

	/**
	 * @param Server $server
	 * @param string $name
	 * @throws ConnectionError
	 */
	public function __construct($server, $name) {
		$this->server = $server;
		$this->name = $name;
	}

	public function connect() {
		if ($this->connection and $this->connection->isValid()) {
			return;
		}
		$command = Server::CLIENT . ' --authentication-file=/proc/self/fd/3' .
			' //' . $this->server->getHost() . '/' . $this->name;
		$this->connection = new Connection($command);
		$this->connection->writeAuthentication($this->server->getUser(), $this->server->getPassword());
		if (!$this->connection->isValid()) {
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

	protected function simpleCommand($command, $path) {
		$path = $this->escapePath($path);
		$cmd = $command . ' ' . $path;
		$output = $this->execute($cmd);
		return $this->parseOutput($output);
	}

	private function getServerTimeZone() {
		if (!$this->serverTimezone) {
			$this->serverTimezone = $this->server->getTimeZone();
		}
		return $this->serverTimezone;
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
		$path = $this->escapePath($path);
		$output = $this->execute('cd ' . $path);
		//check output for errors
		$this->parseOutput($output);
		$output = $this->execute('dir');
		$this->execute('cd /');

		//last line is used space
		array_pop($output);
		$regex = '/^\s*(.*?)\s\s\s\s+(?:([NDHARS]*)\s+)?([0-9]+)\s+(.*)$/';
		//2 spaces, filename, optional type, size, date
		$content = array();
		foreach ($output as $line) {
			if (preg_match($regex, $line, $matches)) {
				list(, $name, $type, $size, $time) = $matches;
				if ($name !== '.' and $name !== '..') {
					$content[$name] = array(
						'size' => intval(trim($size)),
						'type' => (strpos($type, 'D') !== false) ? 'dir' : 'file',
						'time' => strtotime($time . ' ' . $this->getServerTimeZone())
					);
				}
			}
		}
		return $content;
	}

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		return $this->simpleCommand('mkdir', $path);
	}

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		return $this->simpleCommand('rmdir', $path);
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function del($path) {
		//del return a file not found error when trying to delete a folder
		//we catch it so we can check if $path doesn't exist or is of invalid type
		try {
			return $this->simpleCommand('del', $path);
		} catch (NotFoundException $e) {
			//no need to do anything with the result, we just check if this throws the not found error
			try {
				$this->simpleCommand('ls', $path);
			} catch (NotFoundException $e2) {
				throw $e;
			} catch (\Exception $e2) {
				throw new InvalidTypeException();
			}
		}
	}

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function rename($from, $to) {
		$path1 = $this->escapePath($from);
		$path2 = $this->escapePath($to);
		$cmd = 'rename ' . $path1 . ' ' . $path2;
		$output = $this->execute($cmd);
		return $this->parseOutput($output);
	}

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 */
	public function put($source, $target) {
		$path1 = $this->escapeLocalPath($source); //first path is local, needs different escaping
		$path2 = $this->escapePath($target);
		$output = $this->execute('put ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output);
	}

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 */
	public function get($source, $target) {
		$path1 = $this->escapePath($source);
		$path2 = $this->escapeLocalPath($target); //second path is local, needs different escaping
		$output = $this->execute('get ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output);
	}

	/**
	 * Open a readable stream to a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 */
	public function read($source) {
		$source = $this->escapePath($source);
		// since returned stream is closed by the caller we need to create a new instance
		// since we can't re-use the same file descriptor over multiple calls
		$command = Server::CLIENT . ' --authentication-file=/proc/self/fd/3' .
			' //' . $this->server->getHost() . '/' . $this->name
			. ' -c \'get ' . $source . ' /proc/self/fd/5\'';
		$connection = new Connection($command);
		$connection->writeAuthentication($this->server->getUser(), $this->server->getPassword());
		$fh = $connection->getFileOutputStream();
		//save the connection as context of the stream to prevent it going out of scope and cleaning up the resource
		stream_context_set_option($fh, 'file', 'connection', $connection);
		return $fh;
	}

	/**
	 * Open a writable stream to a remote file
	 *
	 * @param string $target
	 * @return resource a write only stream to upload a remote file
	 */
	public function write($target) {
		$target = $this->escapePath($target);
		// since returned stream is closed by the caller we need to create a new instance
		// since we can't re-use the same file descriptor over multiple calls
		$command = Server::CLIENT . ' --authentication-file=/proc/self/fd/3' .
			' //' . $this->server->getHost() . '/' . $this->name
			. ' -c \'put /proc/self/fd/4 ' . $target . '\'';
		$connection = new RawConnection($command);
		$connection->writeAuthentication($this->server->getUser(), $this->server->getPassword());
		$fh = $connection->getFileInputStream();

		// use a close callback to ensure the upload is finished before continuing
		// this also serves as a way to keep the connection in scope
		return CallbackWrapper::wrap($fh, null, null, function () use ($connection) {
			$connection->close(false); // dont terminate, give the upload some time
		});
	}

	/**
	 * @param resource $source
	 * @param callable $callback
	 * @return resource
	 */
	protected function addCloseCallback($source, $callback) {
		$context = stream_context_create(array(
			'callback' => array(
				'source' => $source,
				'close' => $callback
			)
		));
		stream_wrapper_register('callback', '\Icewind\Streams\CallbackWrapper');
		$stream = fopen('callback://', 'r+', false, $context);
		stream_wrapper_unregister('callback');
		return $stream;
	}

	/**
	 * @param string $command
	 * @return array
	 */
	protected function execute($command) {
		$this->connect();
		$this->connection->write($command . PHP_EOL);
		$output = $this->connection->read();
		return $output;
	}

	/**
	 * check output for errors
	 *
	 * @param $lines
	 *
	 * @throws NotFoundException
	 * @throws AlreadyExistsException
	 * @throws AccessDeniedException
	 * @throws NotEmptyException
	 * @throws InvalidTypeException
	 * @throws \Exception
	 * @return bool
	 */
	protected function parseOutput($lines) {
		if (count($lines) === 0) {
			return true;
		} else {
			if (strpos($lines[0], 'does not exist')) {
				throw new NotFoundException();
			}
			$parts = explode(' ', $lines[0]);
			$error = false;
			foreach ($parts as $part) {
				if (substr($part, 0, 9) === 'NT_STATUS') {
					$error = $part;
				}
			}
			switch ($error) {
				case ErrorCodes::PathNotFound:
				case ErrorCodes::ObjectNotFound:
				case ErrorCodes::NoSuchFile:
					throw new NotFoundException();
				case ErrorCodes::NameCollision:
					throw new AlreadyExistsException();
				case ErrorCodes::AccessDenied:
					throw new AccessDeniedException();
				case ErrorCodes::DirectoryNotEmpty:
					throw new NotEmptyException();
				case ErrorCodes::FileIsADirectory:
				case ErrorCodes::NotADirectory:
					throw new InvalidTypeException();
				default:
					throw new Exception();
			}
		}
	}

	/**
	 * @param string $string
	 * @return string
	 */
	protected function escape($string) {
		return escapeshellarg($string);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function escapePath($path) {
		$path = str_replace('/', '\\', $path);
		$path = str_replace('"', '^"', $path);
		return '"' . $path . '"';
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function escapeLocalPath($path) {
		$path = str_replace('"', '\"', $path);
		return '"' . $path . '"';
	}

	public function __destruct() {
		unset($this->connection);
	}
}
