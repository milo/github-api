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

	$clone = $api->withUrl('url://cloned');
	Assert::notSame($api, $clone);
	Assert::same('url://test', $api->getUrl());
	Assert::same('url://cloned', $clone->getUrl());
	Assert::same($api->getClient(), $clone->getClient());

	$token = new Milo\Github\OAuth\Token('hash');
	Assert::null($api->getToken());
	Assert::same($api, $api->setToken($token));
	Assert::same($token, $api->getToken());
	$api->setToken(null);
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
	$request = $api->createRequest('', 'path', [], [], null);
	Assert::same('url://test/path', $request->getUrl());

	# Array to JSON
	$request = $api->createRequest('', '', [], [], ['foo' => 'bar']);
	Assert::same(['content-type' => 'application/json; charset=utf-8'], $request->getHeaders());
	Assert::same('{"foo":"bar"}', $request->getContent());

	# Object to JSON
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
	$request = $api->createRequest('', 'path', [], [], null);
	Assert::same('url://test/path', $request->getUrl());

	$params = ['foo' => 'bar'];
	$api->setDefaultParameters($params);

	Assert::same($params, $api->getDefaultParameters());
	$request = $api->createRequest('', 'path', [], [], null);
	Assert::same('url://test/path?foo=bar', $request->getUrl());

	Assert::same($params, $api->getDefaultParameters());
	$request = $api->createRequest('', 'path', ['foo' => 'fuzz'], [], null);
	Assert::same('url://test/path?foo=fuzz', $request->getUrl());
});


# Api called with absolute URL
test(function() {
	$client = new MockIClient;
	$api = new Milo\Github\Api($client);
	$api->setUrl('url://test');

	$request = $api->createRequest('', 'url://test/path', [], [], null);
	Assert::same('url://test/path', $request->getUrl());

	$request = $api->createRequest('', 'url://tested/path', [], [], null);
	Assert::same('url://test/ed/path', $request->getUrl());

	$request = $api->createRequest('', 'uRl://TeSt/path', [], [], null);
	Assert::same('url://test/path', $request->getUrl());

	# Absolute HTTPS URL with different host
	$request = $api->createRequest('', 'https://example.com', [], [], null);
	Assert::same('https://example.com/', $request->getUrl());
	$request = $api->createRequest('', 'https://example.com/path', [], [], null);
	Assert::same('https://example.com/path', $request->getUrl());

	# Absolute non-HTTPS URL with different host is not allowed (should be?)
	$request = $api->createRequest('', 'http://example.com', [], [], null);
	Assert::same('url://test/http://example.com', $request->getUrl());
});


# Paginator
test(function() {
	$client = new MockIClient;
	$api = new Milo\Github\Api($client);

	Assert::type('Milo\Github\Paginator', $api->paginator(''));
});
