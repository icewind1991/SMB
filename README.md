SMB
===

PHP wrapper for `smbclient` and [`libsmbclient-php`](https://github.com/eduardok/libsmbclient-php)

- Reuses a single `smbclient` instance for multiple requests
- Doesn't leak the password to the process list
- Simple 1-on-1 mapping of SMB commands
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

foreach ($content as $name => $info) {
	echo $name . "\n";
	echo "\tsize :" . $info['size'] . "\n";
}
```

### Using [libsmbclient-php](https://github.com/eduardok/libsmbclient-php) ###

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
