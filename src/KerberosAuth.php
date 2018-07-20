<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Icewind\SMB;

use Icewind\SMB\Exception\Exception;

/**
 * Use existing kerberos ticket to authenticate
 */
class KerberosAuth implements IAuth {

	private $ticketPath = "";

	//not working with nextcloud
        private $saveTicketInMemory = false;

	public function __construct() {
		$this->registerApacheKerberosTicket();
	}

	public function getUsername(): ?string {
		return 'dummy';
	}

	public function getWorkgroup(): ?string {
		return 'dummy';
	}

	public function getPassword(): ?string {
		return null;
	}

	public function getExtraCommandLineArguments(): string {
		return '-k';
	}

	public function setExtraSmbClientOptions($smbClientState): void {
		$success = (bool)smbclient_option_set($smbClientState, SMBCLIENT_OPT_USE_KERBEROS, true);
		$success = $success && smbclient_option_set($smbClientState, SMBCLIENT_OPT_FALLBACK_AFTER_KERBEROS, false);

		if (!$success) {
			throw new Exception("Failed to set smbclient options for kerberos auth");
		}
	}

	private function registerApacheKerberosTicket() {
		// inspired by https://git.typo3.org/TYPO3CMS/Extensions/fal_cifs.git

		if (!extension_loaded("krb5")) {
			return;
		}
		//read apache kerberos ticket cache
		$cacheFile = getenv("KRB5CCNAME");
		if(!$cacheFile) {
			return;
		}
		$krb5 = new \KRB5CCache();
		$krb5->open($cacheFile);
		if(!$krb5->isValid()) {
			return;
		}
		if($this->saveTicketInMemory) {
			putenv("KRB5CCNAME=" . $krb5->getName());
		}
		else {
			//workaround: smbclient is not working with the original apache ticket cache.
			$tmpFilename = tempnam("/tmp", "krb5cc_php_");
			$tmpCacheFile = "FILE:" . $tmpFilename;
			$krb5->save($tmpCacheFile);
			$this->ticketPath = $tmpFilename;
			putenv("KRB5CCNAME=" . $tmpCacheFile);
		}
	}

	public function __destruct() {
		if(!empty($this->ticketPath) && file_exists($this->ticketPath)  && is_file($this->ticketPath)) {
			   unlink($this->ticketPath);
		}
	}

}
