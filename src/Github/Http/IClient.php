<?php

declare(strict_types=1);

namespace Milo\Github\Http;


/**
 * HTTP client interface.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
interface IClient
{
	function request(Request $request): Response;

	/**
	 * @param ?callable(Request $request): void  $callback
	 */
	function onRequest(?callable $callback): static;

	/**
	 * @param ?callable(Response $response): void  $callback
	 */
	function onResponse(?callable $callback): static;
}
