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

	/** @var Storages\ICache|null */
	private $cache;

	/** @var IClient */
	private $client;

	/** @var bool */
	private $forbidRecheck;

	/** @var callable|null */
	private $onResponse;


	/**
	 * @param Storages\ICache
	 * @param IClient
	 * @param bool  forbid checking Github for new data; more or less development purpose only
	 */
	public function __construct(Storages\ICache $cache, IClient $client = null, $forbidRecheck = false)
	{
		$this->cache = $cache;
		$this->client = $client ?: Github\Helpers::createDefaultClient();
		$this->forbidRecheck = (bool) $forbidRecheck;
	}


	/**
	 * @return IClient
	 */
	public function getInnerClient()
	{
		return $this->client;
	}


	/**
	 * @return Response
	 *
	 * @throws BadResponseException
	 */
	public function request(Request $request)
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


	/**
	 * @param  callable|null function(Request $request)
	 * @return self
	 */
	public function onRequest($callback)
	{
		$this->client->onRequest($callback);
		return $this;
	}


	/**
	 * @param  callable|null function(Response $response)
	 * @return self
	 */
	public function onResponse($callback)
	{
		$this->client->onResponse(null);
		$this->onResponse = $callback;
		return $this;
	}


	/**
	 * @return bool
	 */
	protected function isCacheable(Response $response)
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
