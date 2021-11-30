<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$e = Assert::exception(function() {
	new Milo\Github\Storages\FileCache(__DIR__ . DIRECTORY_SEPARATOR . 'non-exists');
}, 'Milo\Github\Storages\MissingDirectoryException', "Directory '%a%non-exists' is missing.");

Assert::null($e->getPrevious());


define('TEMP_DIR', __DIR__ . '/temp.FileCache');
@mkdir(TEMP_DIR);  # @ = directory may exist
Tester\Helpers::purge(TEMP_DIR);

$cache = new Milo\Github\Storages\FileCache(TEMP_DIR);

Assert::null($cache->load('undefined'));

$value = $cache->save('key-1', null);
Assert::null($cache->load('key-1'));

$value = $cache->save('key-2', true);
Assert::true($cache->load('key-2'));

$value = $cache->save('key-3', false);
Assert::false($cache->load('key-3'));

$value = $cache->save('key-4', []);
Assert::same([], $cache->load('key-4'));

$value = $cache->save('key-5', [0, 'a', []]);
Assert::same([0, 'a', []], $cache->load('key-5'));

$value = $cache->save('key-6', new stdClass);
Assert::equal(new stdClass, $cache->load('key-6'));
