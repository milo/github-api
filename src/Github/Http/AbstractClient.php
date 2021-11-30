<?php

declare(strict_types=1);

namespace Milo\Github\Http;

use Milo\Github;


/**
 * Ancestor for HTTP clients. Cares about redirecting and debug events.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
abstract class AbstractClient implements IClient
{
	use Github\Strict;

	/** @var int[]  will follow Location header on these response codes */
	public array $redirectCodes = [
		Response::S301_MOVED_PERMANENTLY,
		Response::S302_FOUND,
		Response::S307_TEMPORARY_REDIRECT,
	];

	/** Maximum redirects per request */
	public int $maxRedirects = 5;

	/** @var callable|null */
	private $onRequest;

	/** @var callable|null */
	private $onResponse;


	/**
	 * @see https://developer.github.com/v3/#http-redirects
	 *
	 * @throws BadResponseException
	 */
	public function request(Request $request): Response
	{
		$request = clone $request;

		$counter = $this->maxRedirects;
		$previous = null;
		do {
			$this->setupRequest($request);

			$this->onRequest && call_user_func($this->onRequest, $request);
			$response = $this->process($request);
			$this->onResponse && call_user_func($this->onResponse, $response);

			$previous = $response->setPrevious($previous);

			if ($counter > 0 && in_array($response->getCode(), $this->redirectCodes) && $response->hasHeader('Location')) {
				/** @todo Use the same HTTP $method for redirection? Set $content to NULL? */
				$request = new Request(
					$request->getMethod(),
					$response->getHeader('Location'),
					$request->getHeaders(),
					$request->getContent()
				);

				$counter--;
				continue;
			}
			break;

		} while (true);

		return $response;
	}


	/** @inheritdoc */
	public function onRequest(?callable $callback): static
	{
		$this->onRequest = $callback;
		return $this;
	}


	/** @inheritdoc */
	public function onResponse(?callable $callback): static
	{
		$this->onResponse = $callback;
		return $this;
	}


	protected function setupRequest(Request $request): void
	{
		$request->addHeader('Expect', '');
	}


	/**
	 * @throws BadResponseException
	 */
	abstract protected function process(Request $request): Response;
}
