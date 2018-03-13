<?php

use Icewind\SMB\NativeServer;
use Icewind\SMB\Server;

require('vendor/autoload.php');

$host = 'localhost';
$user = 'test\test';
$password = 'test';
$share = 'test';

$auth = new \Icewind\SMB\BasicAuth($user, $password);
$serverFactory = new \Icewind\SMB\ServerFactory();

$server = $serverFactory->createServer($host, $auth);

$share = $server->getShare($share);

$files = $share->dir('/');
foreach ($files as $file) {
	echo $file->getName() . "\n";
}
