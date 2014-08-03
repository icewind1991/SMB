<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

/**
 * Low level wrapper for libsmbclient-php for error handling
 */
class NativeState {
	/**
	 * @var resource
	 */
	protected $state;

	protected $handlerSet = false;

	protected $connected = false;

	/**
	 * @return resource
	 */
	public function getState() {
		return $this->state;
	}

	protected function setErrorHandler() {
		if ($this->handlerSet) {
			return;
		}
		$this->handlerSet = true;
		set_error_handler(array($this, 'handleError'));
	}

	protected function restoreErrorHandler() {
		if (!$this->handlerSet) {
			return;
		}
		$this->handlerSet = false;
		restore_error_handler();
	}

	protected function handleError($errorNumber = 0, $errorString = '') {
		$error = smbclient_state_errno($this->state);
		switch ($error) {
			// see error.h
			case 0;
				return;
			case 1:
				throw new ForbiddenException();
			case 2:
				throw new NotFoundException();
			case 17:
				throw new AlreadyExistsException();
			case 20:
				throw new InvalidTypeException();
			case 21:
				throw new InvalidTypeException();
			default:
				if ($errorString) {
					throw new Exception($errorString);
				} else {
					throw new Exception('Unknown error (' . $error . ')');
				}
		}
	}

	/**
	 * @param string $workGroup
	 * @param string $user
	 * @param string $password
	 * @return bool
	 */
	public function init($workGroup, $user, $password) {
		if ($this->connected) {
			return true;
		}
		$this->state = smbclient_state_new();
		$this->setErrorHandler();
		$result = smbclient_state_init($this->state, $workGroup, $user, $password);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		$this->connected = true;
		return $result;
	}

	/**
	 * @param string $uri
	 * @return resource
	 */
	public function opendir($uri) {
		$this->setErrorHandler();
		$result = smbclient_opendir($this->state, $uri);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param resource $dir
	 * @return array
	 */
	public function readdir($dir) {
		$this->setErrorHandler();
		$result = smbclient_readdir($this->state, $dir);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param $dir
	 * @return bool
	 */
	public function closedir($dir) {
		$this->setErrorHandler();
		$result = smbclient_closedir($this->state, $dir);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $old
	 * @param string $new
	 * @return bool
	 */
	public function rename($old, $new) {
		$this->setErrorHandler();
		$result = smbclient_rename($this->state, $old, $this->state, $new);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @return bool
	 */
	public function unlink($uri) {
		$this->setErrorHandler();
		$result = smbclient_unlink($this->state, $uri);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @param int $mask
	 * @return bool
	 */
	public function mkdir($uri, $mask = 0777) {
		$this->setErrorHandler();
		$result = smbclient_mkdir($this->state, $uri, $mask);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @return bool
	 */
	public function rmdir($uri) {
		$this->setErrorHandler();
		$result = smbclient_rmdir($this->state, $uri);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @return array
	 */
	public function stat($uri) {
		$this->setErrorHandler();
		$result = smbclient_stat($this->state, $uri);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param resource $file
	 * @return array
	 */
	public function fstat($file) {
		$this->setErrorHandler();
		$result = smbclient_fstat($this->state, $file);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @param string $mode
	 * @param int $mask
	 * @return resource
	 */
	public function open($uri, $mode, $mask = 0666) {
		$this->setErrorHandler();
		$result = smbclient_open($this->state, $uri, $mode, $mask);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @param int $mask
	 * @return resource
	 */
	public function create($uri, $mask = 0666) {
		$this->setErrorHandler();
		$result = smbclient_creat($this->state, $uri, $mask);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param resource $file
	 * @param int $bytes
	 * @return string
	 */
	public function read($file, $bytes) {
		$this->setErrorHandler();
		$result = smbclient_read($this->state, $file, $bytes);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param resource $file
	 * @param string $data
	 * @param int $length
	 * @return int
	 */
	public function write($file, $data, $length = null) {
		$this->setErrorHandler();
		$result = smbclient_write($this->state, $file, $data, $length);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param resource $file
	 * @param int $offset
	 * @param int $whence SEEK_SET | SEEK_CUR | SEEK_END
	 * @return int
	 */
	public function lseek($file, $offset, $whence = SEEK_SET) {
		$this->setErrorHandler();
		$result = smbclient_lseek($this->state, $file, $offset, $whence);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param resource $file
	 * @param int $size
	 * @return bool
	 */
	public function ftruncate($file, $size) {
		$this->setErrorHandler();
		$result = smbclient_ftruncate($this->state, $file, $size);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	public function close($file) {
		$this->setErrorHandler();
		$result = smbclient_close($this->state, $file);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @param string $key
	 * @return string
	 */
	public function getxattr($uri, $key) {
		$this->setErrorHandler();
		$result = smbclient_getxattr($this->state, $uri, $key);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	/**
	 * @param string $uri
	 * @param string $key
	 * @param string $value
	 * @param int $flags
	 * @return mixed
	 */
	public function setxattr($uri, $key, $value, $flags = 0) {
		$this->setErrorHandler();
		$result = smbclient_setxattr($this->state, $uri, $key, $value, $flags);
		$this->restoreErrorHandler();

		if ($result === false) {
			$this->handleError();
		}
		return $result;
	}

	public function __destruct() {
		if ($this->connected) {
			smbclient_state_free($this->state);
		}
		$this->restoreErrorHandler();
	}
}
