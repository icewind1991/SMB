<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

interface IShare {
	/**
	 * Get the name of the share
	 *
	 * @return string
	 */
	public function getName();

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
	public function get($source, $target);

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
	public function put($source, $target);

	/**
	 * Open a readable stream top a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function read($source);

	/**
	 * Open a writable stream to a remote file
	 *
	 * @param string $target
	 * @return resource a write only stream to upload a remote file
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function write($target);

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
	public function rename($from, $to);

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function del($path);

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
	 * @return \Icewind\SMB\IFileInfo[]
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function dir($path);

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\AlreadyExistsException
	 */
	public function mkdir($path);

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\NotFoundException
	 * @throws \Icewind\SMB\InvalidTypeException
	 */
	public function rmdir($path);
}
