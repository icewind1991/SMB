<?php

require('vendor/autoload.php');

// dfs paths not working
$host = 'dc.domain.local';
$share = 'netlogon';

$auth = new \Icewind\SMB\KerberosApacheAuth();
$serverFactory = new \Icewind\SMB\ServerFactory();

$server = $serverFactory->createServer($host, $auth);

$share = $server->getShare($share);

$files = $share->dir('/');
foreach ($files as $file) {
	echo $file->getName() . "\n";
}
