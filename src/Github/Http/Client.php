<?php

namespace Milo\Github\Http;

use Milo\Github;
use Milo\Github\Storages;


/**
 * HTTP client. If available, uses cURL. Native PHP stream with context otherwise.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Client extends Github\Sanity implements IClient
{
	/** @var int[]  client will follow Location header on these response codes */
	public $redirectCodes = [
		Response::S301_MOVED_PERMANENTLY,
		Response::S302_FOUND,
		Response::S307_TEMPORARY_REDIRECT,
	];


	/** @var int */
	public $maxRedirects = 5;

	/** @var callable */
	private $onRequest;

	/** @var callable */
	private $onResponse;

	/** @var Storages\ICache|NULL */
	private $cache;

	/** @var array */
	private $sslCheck;


	/**
	 * @param  Storages\ICache
	 * @param  bool|string  TRUE = verify remote certificate, string = path to CA file/directory
	 */
	public function __construct(Storages\ICache $cache = NULL, $sslCheck = FALSE)
	{
		$this->cache = $cache;
		$this->sslCheck = $sslCheck;
	}


	/**
	 * @see https://developer.github.com/v3/#http-redirects
	 *
	 * @return Response
	 *
	 * @throws BadResponseException
	 */
	public function request(Request $request)
	{
		$request = clone $request;

		$counter = $this->maxRedirects;
		$previous = NULL;
		do {
			$cacheKey = $this->cacheKey(
				$request->getMethod(),
				$request->getUrl(),

				/** @todo This should depend on Vary: header */
				$request->getHeader('Accept'),
				$request->getHeader('Accept-Encoding'),
				$request->getHeader('Authorization')
			);

			if ($this->cache && ($cached = $this->cache->load($cacheKey))) {
				/** @var $cached Response */
				if ($cached->hasHeader('ETag')) {
					$request->addHeader('If-None-Match', $cached->getHeader('ETag'));
				} elseif ($cached->hasHeader('Last-Modified')) {
					$request->addHeader('If-Modified-Since', $cached->getHeader('Last-Modified'));
				}
			}

			$request->addHeader('Connection', 'close');

			$this->onRequest && call_user_func($this->onRequest, $request);
			$response = $this->streamRequest($request); /** @todo self::curlRequest */
			$this->onResponse && call_user_func($this->onResponse, $response);

			if ($this->cache && $this->isCacheable($response)) {
				$this->cache->save($cacheKey, clone $response);
			}

			if (isset($cached) && $response->getCode() === Response::S304_NOT_MODIFIED) {
				/** @todo Would be responses somehow combined? */
				$previous = $response;
				$response = $cached;
			}

			$response->setPrevious($previous);
			$previous = $response;

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

		} while (TRUE);

		return $response;
	}


	/**
	 * @param  callable|NULL function(Request $request)
	 * @return self
	 */
	public function onRequest($callback)
	{
		$this->onRequest = $callback;
		return $this;
	}


	/**
	 * @param  callable|NULL function(Response $response)
	 * @return self
	 */
	public function onResponse($callback)
	{
		$this->onResponse = $callback;
		return $this;
	}


	/**
	 * @return Response
	 *
	 * @throws BadResponseException
	 */
	protected function streamRequest(Request $request)
	{
		$headerStr = [];
		foreach ($request->getHeaders() as $name => $value) {
			foreach ((array) $value as $v) {
				$headerStr[] = "$name: $v";
			}
		}

		$options = [
			'http' => [
				'method' => $request->getMethod(),
				'header' => implode("\r\n", $headerStr) . "\r\n",
				'follow_location' => 0,  # Github sets the Location header for 201 code too and redirection is not required for us
				'protocol_version' => 1.1,
				'ignore_errors' => TRUE,
			],
		];

		if (($content = $request->getContent()) !== NULL) {
			$options['http']['content'] = $content;
		}

		if ($this->sslCheck) {
			$options['ssl'] = ['verify_peer' => TRUE];
			if (is_string($this->sslCheck)) {
				$options['ssl'][is_dir($this->sslCheck) ? 'capath' : 'cafile'] = $this->sslCheck;
			}
		}

		list($code, $headers, $content) = $this->fileGetContents($request->getUrl(), $options);
		return new Response($code, $headers, $content);
	}


	/**
	 * @return Response
	 *
	 * @throws BadResponseException
	 */
	protected function curlRequest(Request $request)
	{
		/** @todo */
	}


	/**
	 * @return bool
	 */
	protected function isCacheable(Response $response)
	{
		/** @todo Do it properly. Vary:, Pragma:, TTL...  */
		if (!$response->isCode(200)) {
			return FALSE;
		} elseif (preg_match('#max-age=0|must-revalidate#i', $response->getHeader('Cache-Control', ''))) {
			return FALSE;
		}

		return $response->hasHeader('ETag') || $response->hasHeader('Last-Modified');
	}


	/**
	 * @param  string
	 * @param  string...
	 * @return string
	 */
	private function cacheKey($value /*, string ...$values*/)
	{
		return implode('.', func_get_args());
	}


	/**
	 * @internal
	 * @param  string
	 * @param  array
	 * @return array
	 */
	protected function fileGetContents($url, array $contextOptions)
	{
		$context = stream_context_create($contextOptions);

		$e = NULL;
		set_error_handler(function($severity, $message, $file, $line) use (& $e) {
			$e = new \ErrorException($message, 0, $severity, $file, $line, $e);
		}, E_WARNING);

		$content = file_get_contents($url, FALSE, $context);
		restore_error_handler();

		if (!isset($http_response_header)) {
			throw new BadResponseException('Missing HTTP headers, request failed.', 0, $e);
		}

		if (!isset($http_response_header[0]) || !preg_match('~^HTTP/1[.]. (\d{3})~i', $http_response_header[0], $m)) {
			throw new BadResponseException('HTTP status code is missing.');
		}
		unset($http_response_header[0]);

		$headers = [];
		foreach ($http_response_header as $header) {
			list($name, $value) = explode(': ', $header, 2) + [NULL, NULL];
			$headers[$name] = $value;
		}

		return [$m[1], $headers, $content];
	}

}
