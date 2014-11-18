<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class MockIClient implements Milo\Github\Http\IClient
{
	public function request(Milo\Github\Http\Request $request)
	{
		throw new \LogicException;
	}

	public function onRequest($cb) {}
	public function onResponse($cb) {}
}


# Basics
test(function() {
	$client = new MockIClient;
	$api = new Milo\Github\Api($client);

	Assert::same($client, $api->getClient());
	Assert::same('https://api.github.com', $api->getUrl());
	Assert::same($api, $api->setUrl('url://test'));
	Assert::same('url://test', $api->getUrl());

	$token = new Milo\Github\OAuth\Token('hash');
	Assert::null($api->getToken());
	Assert::same($api, $api->setToken($token));
	Assert::same($token, $api->getToken());
	$api->setToken(NULL);
	Assert::null($api->getToken());
});


# createRequest()
test(function() {
	$client = new MockIClient;
	$api = new Milo\Github\Api($client);

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
	$api = new Milo\Github\Api($client);
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


# Paginator
test(function() {
	$client = new MockIClient;
	$api = new Milo\Github\Api($client);

	Assert::type('Milo\Github\Paginator', $api->paginator(''));
});
