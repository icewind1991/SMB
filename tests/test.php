<?php

class Test extends PHPUnit_Framework_TestCase {
	/**
	 * @var SMB\Connection $connection
	 */
	private $connection;

	/**
	 * @var SMB\Share $share
	 */
	private $share;

	/**
	 * @var string $root
	 */
	private $root;

	public function setUp() {
		$this->connection = new SMB\Connection('localhost', 'test', 'test');
		$this->share = $this->connection->getShare('test');
		$this->root = '/' . uniqid();
		$this->share->mkdir($this->root);
	}

	public function tearDown() {
		$this->share->rmdir($this->root);
	}

	public function testDirectory() {
		$this->assertEquals(array(), $this->share->dir($this->root));

		$this->share->mkdir($this->root . '/foo');
		$dirs = $this->share->dir($this->root);
		$this->assertEquals(1, count($dirs));
		$this->assertArrayHasKey('foo', $dirs);

		$this->share->rename($this->root . '/foo', $this->root . '/bar');

		$dirs = $this->share->dir($this->root);
		$this->assertEquals(1, count($dirs));
		$this->assertArrayHasKey('bar', $dirs);

		$this->share->rmdir($this->root . '/bar');
		$this->assertEquals(array(), $this->share->dir($this->root));
	}

	public function testFile() {
		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$size = strlen($text);
		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		file_put_contents($tmpFile1, $text);

		$this->share->put($tmpFile1, $this->root . '/lorem.txt');
		unlink($tmpFile1);

		$files = $this->share->dir($this->root);
		$this->assertEquals(1, count($files));
		$this->assertArrayHasKey('lorem.txt', $files);
		$this->assertEquals($files['lorem.txt']['size'], $size);

		$this->share->rename($this->root . '/lorem.txt', $this->root . '/foo.txt');

		$files = $this->share->dir($this->root);
		$this->assertEquals(1, count($files));
		$this->assertArrayHasKey('foo.txt', $files);

		$tmpFile2 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/foo.txt', $tmpFile2);

		$this->assertEquals($text, file_get_contents($tmpFile2));
		unlink($tmpFile2);

		$this->share->del($this->root . '/foo.txt');
		$this->assertEquals(array(), $this->share->dir($this->root));
	}
}
