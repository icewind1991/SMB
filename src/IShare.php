<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

interface IShare {
	public function connect();

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path);

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 */
	public function get($source, $target);

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function rename($from, $to);

	/**
	 * Get the name of the share
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 */
	public function put($source, $target);

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
	public function dir($path);

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path);

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 */
	public function del($path);

	/**
	 * Open a readable stream top a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 */
	public function read($source);

	/**
	 * Open a writable stream to a remote file
	 *
	 * @param string $target
	 * @return resource a write only stream to upload a remote file
	 */
	public function write($target);
}
