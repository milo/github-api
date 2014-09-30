<?php

/**
 * @author  Miloslav Hůla
 *
 * @testCase
 */


require __DIR__ . '/../../bootstrap.php';


class MockClient implements Milo\Github\Http\IClient
{
	/** @var callable */
	public $onRequest;

	public function request(Milo\Github\Http\Request $request)
	{
		return call_user_func($this->onRequest, $request);
	}

	public function onRequest($foo) { trigger_error('Inner onRequest called: ' . var_export($foo, TRUE), E_USER_NOTICE); }
	public function onResponse($foo) { trigger_error('Inner onResponse called: ' . var_export($foo, TRUE), E_USER_NOTICE); }
}


class MockCache implements Milo\Github\Storages\ICache
{
	private $cache = [];

	public function save($key, $value) { return $this->cache[$key] = $value; }
	public function load($key) { return isset($this->cache[$key]) ? $this->cache[$key] : NULL; }
}


class CachingTestCase extends Tester\TestCase
{
	/** @var Milo\Github\Http\CachedClient */
	private $client;

	/** @var MockClient */
	private $innerClient;


	public function setup()
	{
		$cache = new MockCache;
		$this->innerClient = new MockClient;
		$this->client = new Milo\Github\Http\CachedClient($cache, $this->innerClient);
	}


	public function testBasics()
	{
		Assert::same($this->innerClient, $this->client->getInnerClient());

		Assert::error(function() {
			Assert::same($this->client, $this->client->onRequest('callback-1'));
			Assert::same($this->client, $this->client->onResponse('callback-2'));
		}, [
			[E_USER_NOTICE, "Inner onRequest called: 'callback-1'"],
			[E_USER_NOTICE, 'Inner onResponse called: NULL'],
		]);

		$onResponseCalled = FALSE;
		$this->innerClient->onRequest = function () {
			return new Milo\Github\Http\Response(200, [], '');
		};
		Assert::error(function() use (& $onResponseCalled) {
			$this->client->onResponse(function() use (& $onResponseCalled) { $onResponseCalled = TRUE; });
		}, E_USER_NOTICE, 'Inner onResponse called: NULL');
		$this->client->request(
			new Milo\Github\Http\Request('', '')
		);
		Assert::true($onResponseCalled);
	}


	public function testNoCaching()
	{
		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, [], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '1')
		);
		Assert::same('response-1', $response->getContent());

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '2')
		);
		Assert::same('response-2', $response->getContent());
	}


	public function testETagCaching()
	{
		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, ['ETag' => 'e-tag'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '1')
		);
		Assert::same('response-1', $response->getContent());

		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::same('e-tag', $request->getHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(304, [], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '2')
		);
		Assert::same('response-1', $response->getContent());

		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
	}


	public function testIfModifiedCaching()
	{
		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, ['Last-Modified' => 'today'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '1')
		);
		Assert::same('response-1', $response->getContent());

		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::same('today', $request->getHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(304, [], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '2')
		);
		Assert::same('response-1', $response->getContent());

		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
	}


	public function testRepeatedRequest()
	{
		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) {
			if ($request->hasHeader('If-None-Match')) {
				return new Milo\Github\Http\Response(304, [], 'inner-304');
			}

			return new Milo\Github\Http\Response(200, ['ETag' => '"test"'], 'inner-200');
		};

		$request = new Milo\Github\Http\Request('', '');

		# Empty cache
		$response = $this->client->request($request);
		Assert::same('inner-200', $response->getContent());
		Assert::null($response->getPrevious());

		# From cache
		$response = $this->client->request($request);
		Assert::same('inner-200', $response->getContent());
		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same('inner-304', $response->getPrevious()->getContent());

		# Again
		$response = $this->client->request($request);
		Assert::same('inner-200', $response->getContent());
		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same('inner-304', $response->getPrevious()->getContent());
	}


	public function testForbidRecheckDisabled()
	{
		$client = new Milo\Github\Http\CachedClient(new MockCache, $this->innerClient);

		$count = 0;
		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) use (& $count) {
			$count++;
			return $request->hasHeader('If-None-Match')
				? new Milo\Github\Http\Response(304, [], 'inner-304')
				: new Milo\Github\Http\Response(200, ['ETag' => '"test"'], 'inner-200');
		};

		$request = new Milo\Github\Http\Request('', '');

		$response = $client->request($request);
		Assert::same(1, $count);
		Assert::same('inner-200', $response->getContent());
		Assert::null($response->getPrevious());

		$response = $client->request($request);
		Assert::same(2, $count);
		Assert::same('inner-200', $response->getContent());
		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same('inner-304', $response->getPrevious()->getContent());

		$response = $client->request($request);
		Assert::same(3, $count);
		Assert::same('inner-200', $response->getContent());
		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same('inner-304', $response->getPrevious()->getContent());
	}


	public function testForbidRecheckEnabled()
	{
		$client = new Milo\Github\Http\CachedClient(new MockCache, $this->innerClient, TRUE);

		$count = 0;
		$this->innerClient->onRequest = function (Milo\Github\Http\Request $request) use (& $count) {
			$count++;
			return $request->hasHeader('If-None-Match')
				? new Milo\Github\Http\Response(304, [], 'inner-304')
				: new Milo\Github\Http\Response(200, ['ETag' => '"test"'], 'inner-200');
		};

		$request = new Milo\Github\Http\Request('', '');

		$response = $client->request($request);
		Assert::same(1, $count);
		Assert::same('inner-200', $response->getContent());
		Assert::null($response->getPrevious());

		$response = $client->request($request);
		Assert::same(1, $count);
		Assert::same('inner-200', $response->getContent());
		Assert::null($response->getPrevious());

		$response = $client->request($request);
		Assert::same(1, $count);
		Assert::same('inner-200', $response->getContent());
		Assert::null($response->getPrevious());
	}

}

(new CachingTestCase)->run();
