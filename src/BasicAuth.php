<?php
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Icewind\SMB;

class BasicAuth implements IAuth {
	/** @var string */
	private $username;
	/** @var string|null */
	private $workgroup;
	/** @var string */
	private $password;
	/** @var int|null */
	private $port = null;

	public function __construct(string $username, ?string $workgroup, string $password, int $port = null) {
		$this->username = $username;
		$this->workgroup = $workgroup;
		$this->password = $password;
		$this->port = $port;
	}

	public function getUsername(): ?string {
		return $this->username;
	}

	public function getWorkgroup(): ?string {
		return $this->workgroup;
	}

	public function getPassword(): ?string {
		return $this->password;
	}

	public function getExtraCommandLineArguments(): string {
		$ret = '';
		if ($this->workgroup) $ret .= ' -W ' . escapeshellarg($this->workgroup);
		if ($this->port) $ret .= ' -p ' . escapeshellarg($this->port);
		return $ret;
	}

	public function setExtraSmbClientOptions($smbClientState): void {
		// noop
	}
}
