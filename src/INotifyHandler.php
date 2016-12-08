<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 *
 */

namespace Icewind\SMB;


interface INotifyHandler {

	/**
	 * Get all changes detected since the start of the notify process or the last call to getChanges
	 *
	 * @return Change[]
	 */
	public function getChanges();

	/**
	 * Listen actively to all incoming changes
	 *
	 * Note that this is a blocking process and will cause the process to block forever if not explicitly terminated
	 *
	 * @param callable $callback
	 */
	public function listen($callback);

	/**
	 * Stop listening for changes
	 *
	 * Note that any pending changes will be discarded
	 */
	public function stop();
}
