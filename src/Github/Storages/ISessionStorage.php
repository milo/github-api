<?php

declare(strict_types=1);

namespace Milo\Github\Storages;


/**
 * Cross-request session storage.
 */
interface ISessionStorage
{
	function set(string $name, mixed $value): static;


	function get(string $name): mixed;


	function remove(string $name): static;
}
