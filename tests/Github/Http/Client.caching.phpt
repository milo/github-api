<?php

/**
 * @author  Miloslav Hůla
 *
 * @testCase
 */


require __DIR__ . '/../../bootstrap.php';


class TestClient extends Milo\Github\Http\Client
{
	/** @var callable */
	public $onStreamRequest;

	protected function streamRequest(Milo\Github\Http\Request $request)
	{
		return call_user_func($this->onStreamRequest, $request);
	}
}


class MockCache implements Milo\Github\Storages\ICache
{
	private $cache = [];

	public function save($key, $value) { return $this->cache[$key] = $value; }
	public function load($key) { return isset($this->cache[$key]) ? $this->cache[$key] : NULL; }
}


class CachingTestCase extends Tester\TestCase
{
	/** @var Milo\Github\Http\Client */
	private $client;


	public function setup()
	{
		$cache = new MockCache();
		$this->client = new TestClient($cache);
	}


	public function testNotCached()
	{
		$this->client->onStreamRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, [], "response-{$request->getContent()}");
		};

		$request = new Milo\Github\Http\Request('', '', [], '1');
		$response = $this->client->request($request);
		Assert::same('response-1', $response->getContent());

		$request = new Milo\Github\Http\Request('', '', [], '2');
		$response = $this->client->request($request);
		Assert::same('response-2', $response->getContent());
	}


	public function testETagCaching()
	{
		$this->client->onStreamRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, ['ETag' => 'e-tag'], "response-{$request->getContent()}");
		};

		$request = new Milo\Github\Http\Request('', '', [], '1');
		$response = $this->client->request($request);
		Assert::same('response-1', $response->getContent());

		$this->client->onStreamRequest = function (Milo\Github\Http\Request $request) {
			Assert::same('e-tag', $request->getHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(304, [], "response-{$request->getContent()}");
		};

		$request = new Milo\Github\Http\Request('', '', [], '2');
		$response = $this->client->request($request);
		Assert::same('response-1', $response->getContent());

		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
	}


	public function testIfModifiedCaching()
	{
		$this->client->onStreamRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('If-None-Match'));
			Assert::false($request->hasHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(200, ['Last-Modified' => 'today'], "response-{$request->getContent()}");
		};

		$request = new Milo\Github\Http\Request('', '', [], '1');
		$response = $this->client->request($request);
		Assert::same('response-1', $response->getContent());

		$this->client->onStreamRequest = function (Milo\Github\Http\Request $request) {
			Assert::false($request->hasHeader('ETag'));
			Assert::same('today', $request->getHeader('If-Modified-Since'));

			return new Milo\Github\Http\Response(304, [], "response-{$request->getContent()}");
		};

		$request = new Milo\Github\Http\Request('', '', [], '2');
		$response = $this->client->request($request);
		Assert::same('response-1', $response->getContent());

		Assert::type('Milo\Github\Http\Response', $response->getPrevious());
		Assert::same(304, $response->getPrevious()->getCode());
	}

}

(new CachingTestCase)->run();
