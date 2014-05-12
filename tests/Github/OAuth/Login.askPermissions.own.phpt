<?php

/**
 * @author  Miloslav Hůla
 *
 * @outputMatch Was called
 */

require __DIR__ . '/../../bootstrap.php';


$_SESSION = [];
$config = new Milo\Github\OAuth\Configuration('i', 's');
$storage = new Milo\Github\Storages\SessionStorage;


$login = new Milo\Github\OAuth\Login($config, $storage);
$login->askPermissions('http://', function($url) {
	Assert::match('https://github.com/login/oauth/authorize?client_id=i&redirect_uri=http%3A%2F%2F&scope=&state=%h%', $url);
	echo "Was called";
});

throw new \Exception('Must not be there.');
