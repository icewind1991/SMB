<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB\Command;

class Dir extends Command {
	public function __construct($share) {
		parent::__construct($share);
	}

	public function run($arguments) {
		$path = $this->escapePath($arguments['path']);
		$this->execute('cd ' . $path);
		$output = $this->execute('dir');
		$this->execute('cd /');
		return $this->parseOutput($output);
	}

	/**
	 * @param $lines
	 * @return array
	 */
	protected function parseOutput($lines) {
		//last line is used space
		array_pop($lines);
		$regex = '/^\s*(.*?)\s\s\s\s+(?:([DHARS]*)\s+)?([0-9]+)\s+(.*)$/';
		//2 spaces, filename, optional type, size, date
		$content = array();
		foreach ($lines as $line) {
			if (preg_match($regex,$line,$matches)) {
				list(,$name, $type, $size, $time)=$matches;
				if ($name !== '.' and $name !== '..') {
					$content[$name] = array(
						'size' => intval(trim($size)),
						'type' => (strpos($type,'D')!==false) ? 'dir' : 'file',
						'time' => strtotime($time)
					);
				}
			}
		}
		return $content;
	}
}
