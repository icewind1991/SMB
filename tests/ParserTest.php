<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\ACL;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\Wrapped\FileInfo;
use Icewind\SMB\Wrapped\Parser;

class ParserTest extends \PHPUnit\Framework\TestCase {
	public function modeProvider() {
		return [
			['D', IFileInfo::MODE_DIRECTORY],
			['A', IFileInfo::MODE_ARCHIVE],
			['S', IFileInfo::MODE_SYSTEM],
			['H', IFileInfo::MODE_HIDDEN],
			['R', IFileInfo::MODE_READONLY],
			['N', IFileInfo::MODE_NORMAL],
			['RA', IFileInfo::MODE_READONLY | IFileInfo::MODE_ARCHIVE],
			['RAH', IFileInfo::MODE_READONLY | IFileInfo::MODE_ARCHIVE | IFileInfo::MODE_HIDDEN]
		];
	}

	/**
	 * @dataProvider modeProvider
	 */
	public function testParseMode($string, $mode) {
		$parser = new Parser('UTC');
		$this->assertEquals($mode, $parser->parseMode($string), 'Failed parsing ' . $string);
	}

	public function statProvider() {
		return [
			[
				[
					'altname: test.txt',
					'create_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'access_time:    Tue Oct 15 02:58:48 PM 2013 CEST',
					'write_time:     Sat Oct 12 07:05:58 PM 2013 CEST',
					'change_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'attributes:  (80)',
					'stream: [::$DATA], 29634 bytes'
				],
				[
					'mtime' => strtotime('12 Oct 2013 19:05:58 CEST'),
					'mode'  => IFileInfo::MODE_NORMAL,
					'size'  => 29634
				]
			],
			[
				[
					'altname: folder',
					'create_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'access_time:    Tue Oct 15 02:58:48 PM 2013 CEST',
					'write_time:     Sat Oct 12 07:05:58 PM 2013 CEST',
					'change_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'attributes: D (10)',
					'stream: [::$DATA], 29634 bytes'
				],
				[
					'mtime' => strtotime('12 Oct 2013 19:05:58 CEST'),
					'mode'  => IFileInfo::MODE_DIRECTORY,
					'size'  => 29634
				]
			],
			[
				[
					'altname: .hidden',
					'create_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'access_time:    Tue Oct 15 02:58:48 PM 2013 CEST',
					'write_time:     Sat Oct 12 07:05:58 PM 2013 CEST',
					'change_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'attributes: HA (22)',
					'stream: [::$DATA], 29634 bytes'
				],
				[
					'mtime' => strtotime('12 Oct 2013 19:05:58 CEST'),
					'mode'  => IFileInfo::MODE_HIDDEN + IFileInfo::MODE_ARCHIVE,
					'size'  => 29634
				]
			]
		];
	}

	/**
	 * @dataProvider statProvider
	 */
	public function testStat($output, $stat) {
		$parser = new Parser('UTC');
		$this->assertEquals($stat, $parser->parseStat($output));
	}

	public function dirProvider() {
		return [
			[
				[
					'  .                                   D        0  Tue Aug 26 19:11:56 2014',
					'  ..                                 DR        0  Sun Oct 28 15:24:02 2012',
					'  c.pdf                               N    29634  Sat Oct 12 19:05:58 2013',
					'',
					'                62536 blocks of size 8388608. 57113 blocks available'
				],
				[
					new FileInfo(
						'/c.pdf',
						'c.pdf',
						29634,
						strtotime('12 Oct 2013 19:05:58 CEST'),
						IFileInfo::MODE_NORMAL,
						function () {
							return [];
						}
					)
				]
			]
		];
	}

	/**
	 * @dataProvider dirProvider
	 */
	public function testDir($output, $dir) {
		$parser = new Parser('CEST');
		$this->assertEquals($dir, $parser->parseDir($output, '', function () {
			return [];
		}));
	}

	public function testParseACLRealWorld() {
		$parser = new Parser('CEST');
		$raw = [
			"lp_load_ex: refreshing parameters",
			"Initialising global parameters",
			"Processing section \"[global]\"",
			"added interface docker0 ip=172.17.0.1 bcast=172.17.255.255 netmask=255.255.0.0",
			"added interface br-d8e07730e261 ip=172.18.0.1 bcast=172.18.255.255 netmask=255.255.0.0",
			"Connecting to 192.168.10.187 at port 445",
			"GENSEC backend 'gssapi_spnego' registered",
			"GENSEC backend 'gssapi_krb5' registered",
			"GENSEC backend 'gssapi_krb5_sasl' registered",
			"GENSEC backend 'spnego' registered",
			"GENSEC backend 'schannel' registered",
			"GENSEC backend 'naclrpc_as_system' registered",
			"Cannot do GSE to an IP address",
			"Got challenge flags:",
			"Got NTLMSSP neg_flags=0x628",
			"NTLMSSP: Set final flags:",
			"Got NTLMSSP neg_flags=0x620",
			"NTLMSSP Sign/Seal - Initialising with flags:",
			"Got NTLMSSP neg_flags=0x620",
			"NTLMSSP Sign/Seal - Initialising with flags:",
			"Got NTLMSSP neg_flags=0x620",
			"REVISION:1",
			"CONTROL:SR|PD|DI|DP",
			"OWNER:DESKTOP-MLM38Q5\robin",
			"GROUP:DESKTOP-MLM38Q5\None",
			"ACL:Everyone:ALLOWED/OI|CI/R",
			"ACL:NT AUTHORITY\SYSTEM:ALLOWED/OI|CI/FULL",
			"ACL:DESKTOP\\robin:ALLOWED/OI|CI/FULL",
			"ACL:DESKTOP\\test:ALLOWED/OI|CI/R",
			"ACL:BUILTIN\Administrators:ALLOWED/OI|CI/FULL",
			"Maximum access: 0x120089"
		];

		$expected = [
			"BUILTIN\Administrators" => new ACL(ACL::TYPE_ALLOW, ACL::FLAG_CONTAINER_INHERIT + ACL::FLAG_OBJECT_INHERIT, ACL::MASK_DELETE + ACL::MASK_EXECUTE + ACL::MASK_WRITE + ACL::MASK_READ),
			"Everyone"               => new ACL(ACL::TYPE_ALLOW, ACL::FLAG_CONTAINER_INHERIT + ACL::FLAG_OBJECT_INHERIT, ACL::MASK_READ),
			"NT AUTHORITY\SYSTEM"    => new ACL(ACL::TYPE_ALLOW, ACL::FLAG_CONTAINER_INHERIT + ACL::FLAG_OBJECT_INHERIT, ACL::MASK_DELETE + ACL::MASK_EXECUTE + ACL::MASK_WRITE + ACL::MASK_READ),
			"DESKTOP\\test"          => new ACL(ACL::TYPE_ALLOW, ACL::FLAG_CONTAINER_INHERIT + ACL::FLAG_OBJECT_INHERIT, ACL::MASK_READ),
			"DESKTOP\\robin"         => new ACL(ACL::TYPE_ALLOW, ACL::FLAG_CONTAINER_INHERIT + ACL::FLAG_OBJECT_INHERIT, ACL::MASK_DELETE + ACL::MASK_EXECUTE + ACL::MASK_WRITE + ACL::MASK_READ),
		];
		$result = $parser->parseACLs($raw);
		$this->assertEquals($expected, $result);
	}

	public function testParseACLConstructed() {
		$parser = new Parser('CEST');
		$raw = [
			"REVISION:1",
			"CONTROL:SR|PD|DI|DP",
			"OWNER:DESKTOP-MLM38Q5\robin",
			"GROUP:DESKTOP-MLM38Q5\None",
			"ACL:Everyone:ALLOWED/0x0/READ",
			"ACL:Test:DENIED/0x0/R",
			"ACL:Multiple:ALLOWED/0x0/R|X|D",
			"ACL:Numeric:ALLOWED/0x0/0x10",
			"Maximum access: 0x120089"
		];

		$expected = [
			"Everyone" => new ACL(ACL::TYPE_ALLOW, 0, ACL::MASK_READ + ACL::MASK_EXECUTE),
			"Test"     => new ACL(ACL::TYPE_DENY, 0, ACL::MASK_READ),
			"Multiple" => new ACL(ACL::TYPE_ALLOW, 0, ACL::MASK_READ + ACL::MASK_EXECUTE + ACL::MASK_DELETE),
			"Numeric"  => new ACL(ACL::TYPE_ALLOW, 0, 0x10),
		];
		$result = $parser->parseACLs($raw);
		$this->assertEquals($expected, $result);
	}
}
