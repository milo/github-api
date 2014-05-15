<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class MockIClient implements Milo\Github\Http\IClient
{
	/** @return Milo\Github\Http\Response */
	public function request(Milo\Github\Http\Request $request)
	{
		throw new \LogicException;
	}

	public function onRequest($cb) {}
	public function onResponse($cb) {}
}

class TestApi extends Milo\Github\Api
{
	public function substituteUrlParameters(& $url, array & $parameters)
	{
		return parent::substituteUrlParameters($url, $parameters);
	}
}




# Basics
test(function() {
	$client = new MockIClient;
	$api = new TestApi($client);

	Assert::same($client, $api->getClient());
	Assert::same('https://api.github.com', $api->getUrl());
	Assert::same($api, $api->setUrl('url://test'));
	Assert::same('url://test', $api->getUrl());
});


# substituteUrl()
test(function() {
	$client = new MockIClient;
	$api = new TestApi($client);

	$urls = [
		''      => ['',     ['a' => 'A', 'b' => 'B']],
		'/'     => ['/',    ['a' => 'A', 'b' => 'B']],
		':a'    => ['A',    ['b' => 'B']],
		'/:a'   => ['/A',   ['b' => 'B']],
		':a/'   => ['A/',   ['b' => 'B']],
		'/:a/'  => ['/A/',  ['b' => 'B']],
		'/:a/:b/c' => ['/A/B/c', []],
	];

	foreach ($urls as $url => $result) {
		$params = ['a' => 'A', 'b' => 'B'];
		$api->substituteUrlParameters($url, $params);

		Assert::same($url, $result[0]);
		Assert::same($params, $result[1]);
	}

	Assert::exception(function() use ($api) {
		$url = ':a';
		$params = ['A' => 'a'];
		$api->substituteUrlParameters($url, $params);
	}, 'Milo\Github\MissingParameterException', "Missing parameter 'a' for URL path ':a'.");

	Assert::exception(function() use ($api) {
		$url = ':a:b';
		$params = ['a' => 'A', 'b' => 'B'];
		$api->substituteUrlParameters($url, $params);
	}, 'Milo\Github\MissingParameterException', "Missing parameter 'a:b' for URL path ':a:b'.");
});


# createRequest()
test(function() {
	$client = new MockIClient;
	$api = new TestApi($client);

	# All slashes in URL
	$api->setUrl('url://test/');
	$request = $api->createRequest('METHOD', '/:user/path/', ['user' => 'milo', 'foo' => 'bar'], ['Foo' => 'Bar'], '{content}');
	Assert::same('METHOD', $request->getMethod());
	Assert::same('url://test/milo/path?foo=bar', $request->getUrl());
	Assert::same(['foo' => 'Bar'], $request->getHeaders());

	# No slashes in URL
	$api->setUrl('url://test');
	$request = $api->createRequest('', 'path', [], [], NULL);
	Assert::same('url://test/path', $request->getUrl());

	# Array to JSON
	$request = $api->createRequest('', '', [], [], ['foo' => 'bar']);
	Assert::same(['content-type' => 'application/json; charset=utf-8'], $request->getHeaders());
	Assert::same('{"foo":"bar"}', $request->getContent());

	## Object to JSON
	$request = $api->createRequest('', '', [], [], (object) ['foo' => 'bar']);
	Assert::same(['content-type' => 'application/json; charset=utf-8'], $request->getHeaders());
	Assert::same('{"foo":"bar"}', $request->getContent());
});


# Default parameters
test(function() {
	$client = new MockIClient;
	$api = new TestApi($client);
	$api->setUrl('url://test');

	Assert::same([], $api->getDefaultParameters());
	$request = $api->createRequest('', 'path', [], [], NULL);
	Assert::same('url://test/path', $request->getUrl());

	$params = ['foo' => 'bar'];
	$api->setDefaultParameters($params);

	Assert::same($params, $api->getDefaultParameters());
	$request = $api->createRequest('', 'path', [], [], NULL);
	Assert::same('url://test/path?foo=bar', $request->getUrl());

	Assert::same($params, $api->getDefaultParameters());
	$request = $api->createRequest('', 'path', ['foo' => 'fuzz'], [], NULL);
	Assert::same('url://test/path?foo=fuzz', $request->getUrl());
});
