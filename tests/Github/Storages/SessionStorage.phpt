<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$storage = new Milo\Github\Storages\SessionStorage('test');

unset($_SESSION);
Assert::error(function() {
	$storage = new Milo\Github\Storages\SessionStorage('test');
	$storage->set('foo', 'bar');
}, E_USER_WARNING, 'Start session before using Milo\Github\Storages\SessionStorage::set().');


$_SESSION = [];
Assert::same([], $_SESSION);

$o = (object) ['foo'];
Assert::same($storage, $storage->set('foo', $o));
Assert::same($o, $storage->get('foo'));

Assert::same([
	'test' => ['foo' => $o],
],$_SESSION);

Assert::same($storage, $storage->set('foo', null));
Assert::same([
	'test' => [],
], $_SESSION);



$storage = new Milo\Github\Storages\SessionStorage;
$storage->set('foo', 'baz');
Assert::same([
	'test' => [],
	Milo\Github\Storages\SessionStorage::SESSION_KEY => ['foo' => 'baz'],
], $_SESSION);
