<?php

use Icewind\SMB\NativeServer;
use Icewind\SMB\Server;

require('vendor/autoload.php');

$host = 'localhost';
$user = 'test';
$password = 'test';
$share = 'test';

if (Server::NativeAvailable()) {
	$server = new NativeServer($host, $user, $password);
} else {
	$server = new Server($host, $user, $password);
}

$share = $server->getShare($share);

$files = $share->dir('/');
foreach ($files as $file) {
	echo $file->getName() . "\n";
}
