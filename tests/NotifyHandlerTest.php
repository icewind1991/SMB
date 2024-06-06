<?php
/**
 * SPDX-FileCopyrightText: 2016 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: MIT
 */

namespace Icewind\SMB\Test;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\Change;
use Icewind\SMB\Exception\AlreadyExistsException;
use Icewind\SMB\Exception\Exception;
use Icewind\SMB\Exception\RevisionMismatchException;
use Icewind\SMB\INotifyHandler;
use Icewind\SMB\IShare;
use Icewind\SMB\Options;
use Icewind\SMB\System;
use Icewind\SMB\TimeZoneProvider;
use Icewind\SMB\Wrapped\Server;

class NotifyHandlerTest extends TestCase {
	/**
	 * @var \Icewind\SMB\Server $server
	 */
	private $server;

	private $config;

	public function setUp(): void {
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new Server(
			$this->config->host,
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			new System(),
			new TimeZoneProvider(new System()),
			new Options()
		);
	}

	/**
	 * sometimes smb adds modified changes in the mix for shits and giggles
	 *
	 * filter them out so we can compare changes properly
	 *
	 * @param Change[] $changes
	 * @return Change[]
	 */
	private function filterModifiedChanges(array $changes) {
		return array_values(array_filter($changes, function (Change $change) {
			return $change->getCode() !== INotifyHandler::NOTIFY_MODIFIED;
		}));
	}

	public function testGetChanges() {
		$share = $this->server->getShare($this->config->share);
		$expected = [
			new Change(INotifyHandler::NOTIFY_ADDED, 'source.txt'),
			new Change(INotifyHandler::NOTIFY_RENAMED_OLD, 'source.txt'),
			new Change(INotifyHandler::NOTIFY_RENAMED_NEW, 'target.txt'),
			new Change(INotifyHandler::NOTIFY_REMOVED, 'target.txt'),
		];

		for ($i = 0; $i < 5; $i++) {
			$process = $share->notify('');

			usleep(1000 * 100);// give it some time to start listening

			$share->put(__FILE__, 'source.txt');
			$share->rename('source.txt', 'target.txt');
			$share->del('target.txt');
			usleep(1000 * 100);// give it some time

			try {
				$changes = $process->getChanges();
			} catch (RevisionMismatchException $e) {
				$this->markTestSkipped("notify not supported with configured smb version");
			}

			$changes = array_filter($changes, function (Change $change) {
				return $change->getPath()[0] !== '.';
			});

			$process->stop();

			$changes = $this->filterModifiedChanges($changes);
			$this->assertCount(4, $changes);
			if ($changes[1]->getCode() === INotifyHandler::NOTIFY_REMOVED) {
				// sometimes during testing, the move isn't properly recognized with older smb versions, retry a few times
				usleep(1000 * 100);
				continue;
			}

			$this->assertEquals($expected, $changes);
			break;
		}
	}

	public function testChangesSubdir() {
		$share = $this->server->getShare($this->config->share);

		try {
			$share->mkdir('sub');
		} catch (AlreadyExistsException $e) {
		}
		$process = $share->notify('sub');
		usleep(1000 * 100);// give it some time to start listening
		$share->put(__FILE__, 'sub/source.txt');
		$share->del('sub/source.txt');
		usleep(1000 * 100);// give it some time

		try {
			$changes = $process->getChanges();
		} catch (RevisionMismatchException $e) {
			$this->markTestSkipped("notify not supported with configured smb version");
		}
		$process->stop();

		$expected = [
			new Change(INotifyHandler::NOTIFY_ADDED, 'sub/source.txt'),
			new Change(INotifyHandler::NOTIFY_REMOVED, 'sub/source.txt'),
		];

		$share->rmdir('sub');
		$this->assertEquals($expected, $this->filterModifiedChanges($changes));
	}

	public function testListen() {
		$share = $this->server->getShare($this->config->share);
		$process = $share->notify('');

		usleep(1000 * 100);// give it some time to start listening

		$share->put(__FILE__, 'source.txt');
		$share->del('source.txt');

		$results = [];

		// the notify process buffers incoming messages so callback will be triggered for the above changes
		try {
			$process->listen(function ($change) use (&$results) {
				$results = $change;
				return false; // stop listening
			});
		} catch (RevisionMismatchException $e) {
			$this->markTestSkipped("notify not supported with configured smb version");
		}
		$this->assertEquals($results, new Change(INotifyHandler::NOTIFY_ADDED, 'source.txt'));
	}

	public function testStopped() {
		$share = $this->server->getShare($this->config->share);
		$process = $share->notify('');
		$process->stop();
		$this->assertEquals([], $process->getChanges());
	}

	public function testListenAfterGetChanges() {
		$share = $this->server->getShare($this->config->share);
		$process = $share->notify('');

		usleep(1000 * 100);// give it some time to start listening

		$share->put(__FILE__, 'source.txt');
		$share->del('source.txt');
		usleep(1000 * 100);// give it some time

		try {
			$changes = $process->getChanges();
		} catch (RevisionMismatchException $e) {
			$this->markTestSkipped("notify not supported with configured smb version");
		}
		$expected = [
			new Change(INotifyHandler::NOTIFY_ADDED, 'source.txt'),
			new Change(INotifyHandler::NOTIFY_REMOVED, 'source.txt'),
		];

		$this->assertEquals($expected, $this->filterModifiedChanges($changes));

		usleep(1000 * 200);

		$share->put(__FILE__, 'source2.txt');
		$share->del('source2.txt');

		$results = null;

		// the notify process buffers incoming messages so callback will be triggered for the above changes
		$process->listen(function ($change) use (&$results) {
			$results = $change;
			return false; // stop listening
		});
		$this->assertNotNull($results);
	}

	public function testNoStdBuf(): void {
		$this->requireBackendEnv('smbclient');
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$system = $this->getMockBuilder(System::class)
			->onlyMethods(['getStdBufPath'])
			->getMock();
		$system->method('getStdBufPath')
			->willReturn(null);
		$server = new Server(
			$this->config->host,
			new BasicAuth(
				$this->config->user,
				'test',
				$this->config->password
			),
			$system,
			new TimeZoneProvider(new System()),
			new Options()
		);
		$share = $server->getShare($this->config->share);

		$this->expectException(Exception::class);
		$share->notify('');
	}
}
