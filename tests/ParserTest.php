<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Test;


use Icewind\SMB\IFileInfo;
use Icewind\SMB\Wrapped\FileInfo;

class ParserTest extends \PHPUnit_Framework_TestCase {
	public function modeProvider() {
		return array(
			array('D', IFileInfo::MODE_DIRECTORY),
			array('A', IFileInfo::MODE_ARCHIVE),
			array('S', IFileInfo::MODE_SYSTEM),
			array('H', IFileInfo::MODE_HIDDEN),
			array('R', IFileInfo::MODE_READONLY),
			array('N', IFileInfo::MODE_NORMAL),
			array('RA', IFileInfo::MODE_READONLY | IFileInfo::MODE_ARCHIVE),
			array('RAH', IFileInfo::MODE_READONLY | IFileInfo::MODE_ARCHIVE | IFileInfo::MODE_HIDDEN)
		);
	}
	/**
	 * @dataProvider modeProvider
	 */
	public function testParseMode($string, $mode) {
		$parser = new \Icewind\SMB\Wrapped\Parser('UTC');
		$this->assertEquals($mode, $parser->parseMode($string), 'Failed parsing ' . $string);
	}

	public function statProvider() {
		return array(
			array(
				array(
					'altname: test.txt',
					'create_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'access_time:    Tue Oct 15 02:58:48 PM 2013 CEST',
					'write_time:     Sat Oct 12 07:05:58 PM 2013 CEST',
					'change_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'attributes:  (80)',
					'stream: [::$DATA], 29634 bytes'
				),
				array(
					'mtime' => strtotime('12 Oct 2013 19:05:58 CEST'),
					'mode' => IFileInfo::MODE_NORMAL,
					'size' => 29634
				)
			),
			array(
				array(
					'altname: folder',
					'create_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'access_time:    Tue Oct 15 02:58:48 PM 2013 CEST',
					'write_time:     Sat Oct 12 07:05:58 PM 2013 CEST',
					'change_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'attributes: D (10)',
					'stream: [::$DATA], 29634 bytes'
				),
				array(
					'mtime' => strtotime('12 Oct 2013 19:05:58 CEST'),
					'mode' => IFileInfo::MODE_DIRECTORY,
					'size' => 29634
				)
			),
			array(
				array(
					'altname: .hidden',
					'create_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'access_time:    Tue Oct 15 02:58:48 PM 2013 CEST',
					'write_time:     Sat Oct 12 07:05:58 PM 2013 CEST',
					'change_time:    Sat Oct 12 07:05:58 PM 2013 CEST',
					'attributes: HA (22)',
					'stream: [::$DATA], 29634 bytes'
				),
				array(
					'mtime' => strtotime('12 Oct 2013 19:05:58 CEST'),
					'mode' => IFileInfo::MODE_HIDDEN + IFileInfo::MODE_ARCHIVE,
					'size' => 29634
				)
			)
		);
	}

	/**
	 * @dataProvider statProvider
	 */
	public function testStat($output, $stat) {
		$parser = new \Icewind\SMB\Wrapped\Parser('UTC');
		$this->assertEquals($stat, $parser->parseStat($output));
	}

	public function dirProvider() {
		return array(
			array(
				array(
					'  .                                   D        0  Tue Aug 26 19:11:56 2014',
					'  ..                                 DR        0  Sun Oct 28 15:24:02 2012',
					'  c.pdf                               N    29634  Sat Oct 12 19:05:58 2013',
					'',
					'                62536 blocks of size 8388608. 57113 blocks available'
				),
				array(
					new FileInfo('/c.pdf', 'c.pdf', 29634, strtotime('12 Oct 2013 19:05:58 CEST'),
						IFileInfo::MODE_NORMAL)
				)
			)
		);
	}

	/**
	 * @dataProvider dirProvider
	 */
	public function testDir($output, $dir) {
		$parser = new \Icewind\SMB\Wrapped\Parser('CEST');
		$this->assertEquals($dir, $parser->parseDir($output, ''));
	}
}
