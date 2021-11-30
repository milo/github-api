<?php

declare(strict_types=1);

namespace Milo\Github\Storages;


interface ICache
{
	/**
	 * @return mixed  stored value
	 */
	function save(string $key, mixed $value): mixed;


	function load(string $key): mixed;
}
