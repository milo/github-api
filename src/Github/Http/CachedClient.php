<?php

declare(strict_types=1);

namespace Milo\Github\Http;

use Milo\Github;
use Milo\Github\Storages;


/**
 * Caching for HTTP clients.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class CachedClient implements IClient
{
	use Github\Strict;

	private IClient $client;

	/** @var ?callable(Response $response): void */
	private $onResponse;


	/**
	 * @param  bool $forbidRecheck  Forbid checking GitHub for new data; more or less development purpose only
	 */
	public function __construct(
		private Storages\ICache $cache,
		IClient $client = null,
		private bool $forbidRecheck = false,
	) {
		$this->client = $client ?: Github\Helpers::createDefaultClient();
	}


	public function getInnerClient(): IClient
	{
		return $this->client;
	}


	/**
	 * @throws BadResponseException
	 */
	public function request(Request $request): Response
	{
		$request = clone $request;

		$cacheKey = implode('.', [
			$request->getMethod(),
			$request->getUrl(),

			/** @todo This should depend on Vary: header */
			$request->getHeader('Accept'),
			$request->getHeader('Accept-Encoding'),
			$request->getHeader('Authorization')
		]);

		if ($cached = $this->cache->load($cacheKey)) {
			if ($this->forbidRecheck) {
				$cached = clone $cached;
				$this->onResponse && call_user_func($this->onResponse, $cached);
				return $cached;
			}

			/** @var $cached Response */
			if ($cached->hasHeader('Last-Modified')) {
				$request->addHeader('If-Modified-Since', $cached->getHeader('Last-Modified'));
			}
			if ($cached->hasHeader('ETag')) {
				$request->addHeader('If-None-Match', $cached->getHeader('ETag'));
			}
		}

		$response = $this->client->request($request);

		if ($this->isCacheable($response)) {
			$this->cache->save($cacheKey, clone $response);
		}

		if (isset($cached) && $response->getCode() === Response::S304_NOT_MODIFIED) {
			$cached = clone $cached;

			/** @todo Should be responses somehow combined into one? */
			$response = $cached->setPrevious($response);
		}

		$this->onResponse && call_user_func($this->onResponse, $response);

		return $response;
	}


	/** @inheritdoc */
	public function onRequest(?callable $callback): static
	{
		$this->client->onRequest($callback);
		return $this;
	}


	/** @inheritdoc */
	public function onResponse(?callable $callback): static
	{
		$this->client->onResponse(null);
		$this->onResponse = $callback;
		return $this;
	}


	protected function isCacheable(Response $response): bool
	{
		/** @todo Do it properly. Vary:, Pragma:, TTL...  */
		if (!$response->isCode(200)) {
			return false;

		} elseif (preg_match('#max-age=0|must-revalidate#i', $response->getHeader('Cache-Control', ''))) {
			return false;
		}

		return $response->hasHeader('ETag') || $response->hasHeader('Last-Modified');
	}
}
