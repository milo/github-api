<?php

/**
 * @author  Miloslav Hůla
 *
 * @httpCode 302
 */

require __DIR__ . '/../../bootstrap.php';
Assert::true(TRUE);


$_SESSION = [];
$config = new Milo\Github\OAuth\Configuration('', '');
$storage = new Milo\Github\Storages\SessionStorage;


$login = new Milo\Github\OAuth\Login($config, $storage);
$login->askPermissions('http://');

throw new \Exception('Must not be there.');
