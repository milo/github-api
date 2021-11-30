<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 *
 * @testCase
 */


require __DIR__ . '/../../bootstrap.php';

use Milo\Github\Http;


class MockClientCounter implements Http\IClient
{
	/** @var callable */
	public $onRequest;

	public int $requestCount = 0;


	public function request(Http\Request $request): Http\Response
	{
		$response = call_user_func($this->onRequest, $request);
		$this->requestCount++;
		return $response;
	}

	public function onRequest(?callable $callback): static
	{
		trigger_error('Inner onRequest called: ' . var_export($callback, true), E_USER_NOTICE);
		return $this;
	}

	public function onResponse(?callable $callback): static
	{
		trigger_error('Inner onResponse called: ' . var_export($callback, true), E_USER_NOTICE);
		return $this;
	}
}


class MockCache implements Milo\Github\Storages\ICache
{
	private array $cache = [];

	public function save(string $key, mixed $value): mixed
	{
		return $this->cache[$key] = $value;
	}

	public function load(string $key): mixed
	{
		return $this->cache[$key] ?? null;
	}
}


class CachingTestCase extends Tester\TestCase
{
	private Http\CachedClient $client;

	private MockClientCounter $innerClient;


	public function setup()
	{
		$cache = new MockCache;
		$this->innerClient = new MockClientCounter;
		$this->client = new Http\CachedClient($cache, $this->innerClient);

		$this->innerClient->onRequest = function (Http\Request $request) {
			return $request->hasHeader('If-None-Match')
				? new Http\Response(304, [], "inner-304-{$request->getContent()}")
				: new Http\Response(200, ['ETag' => '"inner"'], "inner-200-{$request->getContent()}");
		};
	}


	public function testSetOnRequestOnResponseCallbacks()
	{
		Assert::same($this->innerClient, $this->client->getInnerClient());

		$cb = fn() => null;

		Assert::error(function() use ($cb) {
			Assert::same($this->client, $this->client->onRequest($cb));
			Assert::same($this->client, $this->client->onResponse($cb));
		}, [
			[E_USER_NOTICE, "Inner onRequest called: Closure::%A%"],
			[E_USER_NOTICE, 'Inner onResponse called: NULL'],
		]);

		$onResponseCalled = false;
		Assert::error(function() use (&$onResponseCalled) {
			$this->client->onResponse(function() use (&$onResponseCalled) {
				$onResponseCalled = true;
			});
		}, E_USER_NOTICE, 'Inner onResponse called: NULL');

		$this->client->request(new Http\Request('', ''));
		Assert::true($onResponseCalled);

		Assert::same(1, $this->innerClient->requestCount);
	}


	public function testNoCaching()
	{
		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Http\Response(200, [], "response-{$request->getContent()}");
		};

		$response = $this->client->request(new Http\Request('', '', [], '1'));
		Assert::same('response-1', $response->getContent());
		Assert::same(1, $this->innerClient->requestCount);

		$response = $this->client->request(new Http\Request('', '', [], '2'));
		Assert::same('response-2', $response->getContent());
		Assert::same(2, $this->innerClient->requestCount);
	}


	public function testETagCaching()
	{
		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Http\Response(200, ['ETag' => 'e-tag'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(new Http\Request('', '', [], '1'));
		Assert::same('response-1', $response->getContent());
		Assert::same(1, $this->innerClient->requestCount);


		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::same('e-tag', $request->getHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Http\Response(304, [], "response-{$request->getContent()}");
		};
		$response = $this->client->request(new Http\Request('', '', [], '2'));
		Assert::same('response-1', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
		Assert::same(2, $this->innerClient->requestCount);
	}


	public function testIfModifiedCaching()
	{
		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Http\Response(200, ['Last-Modified' => 'today'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(new Http\Request('', '', [], '1'));
		Assert::same('response-1', $response->getContent());
		Assert::same(1, $this->innerClient->requestCount);


		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::same('today', $request->getHeader('If-Modified-Since'));

			return new Http\Response(304, [], "response-{$request->getContent()}");
		};

		$response = $this->client->request(new Http\Request('', '', [], '2'));
		Assert::same('response-1', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
		Assert::same(2, $this->innerClient->requestCount);
	}


	public function testPreferIfModifiedAgainstETag()
	{
		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Http\Response(200, ['Last-Modified' => 'today', 'ETag' => 'e-tag'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(new Http\Request('', '', [], '1'));
		Assert::same('response-1', $response->getContent());
		Assert::same(1, $this->innerClient->requestCount);


		$this->innerClient->onRequest = function (Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::same('today', $request->getHeader('If-Modified-Since'));

			return new Http\Response(304, [], "response-{$request->getContent()}");
		};

		$response = $this->client->request(new Http\Request('', '', [], '2'));
		Assert::same('response-1', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
		Assert::same(2, $this->innerClient->requestCount);
	}


	public function testRepeatedRequest()
	{
		$request = new Http\Request('', '', [], 'same');

		# Empty cache
		$response = $this->client->request($request);
		Assert::same('inner-200-same', $response->getContent());
		Assert::null($response->getPrevious());
		Assert::same(1, $this->innerClient->requestCount);

		# From cache
		$response = $this->client->request($request);
		Assert::same('inner-200-same', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same('inner-304-same', $response->getPrevious()->getContent());
		Assert::same(2, $this->innerClient->requestCount);

		# Again
		$response = $this->client->request($request);
		Assert::same('inner-200-same', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same('inner-304-same', $response->getPrevious()->getContent());
		Assert::same(3, $this->innerClient->requestCount);
	}


	public function testForbidRecheckDisabled()
	{
		$request = new Http\Request('', '', [], 'disabled');

		$response = $this->client->request($request);
		Assert::same('inner-200-disabled', $response->getContent());
		Assert::null($response->getPrevious());
		Assert::same(1, $this->innerClient->requestCount);

		$response = $this->client->request($request);
		Assert::same('inner-200-disabled', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same('inner-304-disabled', $response->getPrevious()->getContent());
		Assert::same(2, $this->innerClient->requestCount);

		$response = $this->client->request($request);
		Assert::same('inner-200-disabled', $response->getContent());
		Assert::type(Milo\Github\Http\Response::class, $response->getPrevious());
		Assert::same('inner-304-disabled', $response->getPrevious()->getContent());
		Assert::same(3, $this->innerClient->requestCount);
	}


	public function testForbidRecheckEnabled()
	{
		$this->client = new Http\CachedClient(new MockCache, $this->innerClient, true);

		$request = new Http\Request('', '', [], 'enabled');

		$response = $this->client->request($request);
		Assert::same('inner-200-enabled', $response->getContent());
		Assert::null($response->getPrevious());
		Assert::same(1, $this->innerClient->requestCount);

		$response = $this->client->request($request);
		Assert::same('inner-200-enabled', $response->getContent());
		Assert::null($response->getPrevious());
		Assert::same(1, $this->innerClient->requestCount);

		$response = $this->client->request($request);
		Assert::same('inner-200-enabled', $response->getContent());
		Assert::null($response->getPrevious());
		Assert::same(1, $this->innerClient->requestCount);
	}
}

(new CachingTestCase)->run();
