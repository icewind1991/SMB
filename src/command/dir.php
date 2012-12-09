<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Dir extends Simple {
	public function __construct($connection) {
		parent::__construct($connection);
		$this->command = 'cd';
	}

	public function run($arguments) {
		$arguments['postfix']=' ; dir';
		return parent::run($arguments);
	}

	/**
	 * @param $lines
	 * @return array
	 */
	protected function parseOutput($lines) {
		//last line is used space
		array_pop($lines);
		$content = array();
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line) {
				list($name, $meta) = explode(" ", $line, 2);
				if ($name !== '.' and $name !== '..') {
					list($type, $meta) = explode(" ", trim($meta), 2);
					list($size, $time) = explode(" ", trim($meta), 2);
					$content[$name] = array(
						'size' => intval(trim($size)),
						'type' => ($type === 'D') ? 'dir' : 'file',
						'time' => strtotime($time)
					);
				}
			}
		}
		return $content;
	}
}
