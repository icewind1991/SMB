SMB
===

[![Code Coverage](https://scrutinizer-ci.com/g/icewind1991/SMB/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/icewind1991/SMB/?branch=master)
[![Build Status](https://travis-ci.org/icewind1991/SMB.svg?branch=master)](https://travis-ci.org/icewind1991/SMB)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/icewind1991/SMB/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/icewind1991/SMB/?branch=master)

PHP wrapper for `smbclient` and [`libsmbclient-php`](https://github.com/eduardok/libsmbclient-php)

- Reuses a single `smbclient` instance for multiple requests
- Doesn't leak the password to the process list
- Simple 1-on-1 mapping of SMB commands
- A stream-based api to remove the need for temporary files
- Support for using libsmbclient directly trough [`libsmbclient-php`](https://github.com/eduardok/libsmbclient-php)

Examples
----

### Upload a file ###

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$fileToUpload = __FILE__;

$server = new Server('localhost', 'test', 'test');
$share = $server->getShare('test');
$share->put($fileToUpload, 'example.txt');
```

### Download a file ###

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$target = __DIR__ . '/target.txt';

$server = new Server('localhost', 'test', 'test');
$share = $server->getShare('test');
$share->get('example.txt', $target);
```

### List shares on the remote server ###

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$server = new Server('localhost', 'test', 'test');
$shares = $server->listShares();

foreach ($shares as $share) {
	echo $share->getName() . "\n";
}
```

### List the content of a folder ###

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$server = new Server('localhost', 'test', 'test');
$share = $server->getShare('test');
$content = $share->dir('test');

foreach ($content as $info) {
	echo $info->getName() . "\n";
	echo "\tsize :" . $info->getSize() . "\n";
}
```

### Using read streams

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$server = new Server('localhost', 'test', 'test');
$share = $server->getShare('test');

$fh = $share->read('test.txt');
echo fread($fh, 4086);
fclose($fh);
```

### Using write streams

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$server = new Server('localhost', 'test', 'test');
$share = $server->getShare('test');

$fh = $share->write('test.txt');
fwrite($fh, 'bar');
fclose($fh);
```

### Using libsmbclient-php ###

Install [libsmbclient-php](https://github.com/eduardok/libsmbclient-php)

```php
<?php
use Icewind\SMB\Server;
use Icewind\SMB\NativeServer;

require('vendor/autoload.php');

$fileToUpload = __FILE__;

if (Server::NativeAvailable()) {
    $server = new NativeServer('localhost', 'test', 'test');
} else {
    echo 'libsmbclient-php not available, falling back to wrapping smbclient';
    $server = new Server('localhost', 'test', 'test');
}
$share = $server->getShare('test');
$share->put($fileToUpload, 'example.txt');
```

### Using notify

```php
<?php
use Icewind\SMB\Server;

require('vendor/autoload.php');

$server = new Server('localhost', 'test', 'test');
$share = $server->getShare('test');

$share->notify('')->listen(function (\Icewind\SMB\Change $change) {
	echo $change->getCode() . ': ' . $change->getPath() . "\n";
});
```

## Testing SMB

Use the following steps to check if the library can connect to your SMB share.

1. Clone this repository or download the source as [zip](https://github.com/icewind1991/SMB/archive/master.zip)
2. Make sure [composer](https://getcomposer.org/) is installed
3. Run `composer install` in the root of the repository
4. Edit `example.php` with the relevant settings for your share.
5. Run `php example.php`

If everything works correctly then the contents of the share should be outputted.
