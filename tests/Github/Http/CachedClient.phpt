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

	public function onRequest($callback) {}
	public function onResponse($callback) {}
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
	private $mockClient;


	public function setup()
	{
		$cache = new MockCache;
		$this->mockClient = new MockClient;
		$this->client = new \Milo\Github\Http\CachedClient($cache, $this->mockClient);
	}


	public function testNoCaching()
	{
		$this->mockClient->onRequest = function (Milo\Github\Http\Request $request) {
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
		$this->mockClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, ['ETag' => 'e-tag'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '1')
		);
		Assert::same('response-1', $response->getContent());

		$this->mockClient->onRequest = function (Milo\Github\Http\Request $request) {
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
		$this->mockClient->onRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, ['Last-Modified' => 'today'], "response-{$request->getContent()}");
		};

		$response = $this->client->request(
			new Milo\Github\Http\Request('', '', [], '1')
		);
		Assert::same('response-1', $response->getContent());

		$this->mockClient->onRequest = function (Milo\Github\Http\Request $request) {
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

}

(new CachingTestCase)->run();
