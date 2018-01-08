<?php
use Icewind\SMB\NativeServer;
use Icewind\SMB\Server;

require('vendor/autoload.php');

if (Server::NativeAvailable()) {
	$server = new NativeServer('localhost', 'test', 'test');
} else {
	$server = new Server('localhost', 'test', 'test');
}

$share = $server->getShare('test');

$files = $share->dir('/');
foreach ($files as $file) {
	echo $file->getName() . "\n";
}
