<?php
/**
 * Copyright (c) 2015 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

class TimeZoneProvider implements ITimeZoneProvider {
	/**
	 * @var string[]
	 */
	private $timeZones = [];

	/**
	 * @var ISystem
	 */
	private $system;

	/**
	 * @param ISystem $system
	 */
	public function __construct(ISystem $system) {
		$this->system = $system;
	}

	public function get($host) {
		if (!isset($this->timeZones[$host])) {
			$net = $this->system->getNetPath();
			if ($net && $host) {
				$command = sprintf('%s time zone -S %s',
					$net,
					escapeshellarg($host)
				);
				$timeZone = exec($command);
				if (!$timeZone) {
					$timeZone = date_default_timezone_get();
				}
				$this->timeZones[$host] = $timeZone;
			} else { // fallback to server timezone
				$this->timeZones[$host] = date_default_timezone_get();
			}
		}
		return $this->timeZones[$host];
	}
}
