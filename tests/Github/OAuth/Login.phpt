<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


class MockClient implements Milo\Github\Http\IClient
{
	public $onRequest;

	public function request(Milo\Github\Http\Request $request)
	{
		return call_user_func($this->onRequest, $request);
	}

	public function onRequest($cb) {}
	public function onResponse($cb) {}
}


$_SESSION = [];
$config = new Milo\Github\OAuth\Configuration('c-id', 'c-secret');
$storage = new Milo\Github\Storages\SessionStorage;
$client = new MockClient;


$login = new Milo\Github\OAuth\Login($config, $storage, $client);
Assert::same($client, $login->getClient());


# Has token
Assert::false($login->hasToken());
Assert::exception(function() use ($login) {
	$login->getToken();
}, 'Milo\Github\LogicException', 'Token has not been obtained yet.');


# Obtain token by POST
$storage->set('auth.state', '*****');
$client->onRequest = function(Milo\Github\Http\Request $request) {
	Assert::same('POST', $request->getMethod());
	Assert::same('https://github.com/login/oauth/access_token', $request->getUrl());
	Assert::same('client_id=c-id&client_secret=c-secret&code=tmp-code', $request->getContent());
	Assert::same('application/json', $request->getHeader('Accept'));
	Assert::same('application/x-www-form-urlencoded', $request->getHeader('Content-Type'));

	return new Milo\Github\Http\Response(200, [], '{"access_token":"hash","token_type":"type","scope":"a,b,c"}');
};
$token = $login->obtainToken('tmp-code', '*****');
Assert::same('hash', $token->getValue());
Assert::same('type', $token->getType());
Assert::same(['a','b','c'], $token->getScopes());

Assert::true($login->hasToken());
Assert::type('Milo\Github\OAuth\Token', $login->getToken());

Assert::same($login, $login->dropToken());
Assert::false($login->hasToken());


# Obtain token with empty scopes
$storage->set('auth.state', '*****');
$client->onRequest = function(Milo\Github\Http\Request $request) {
	return new Milo\Github\Http\Response(200, [], '{"access_token":"hash","token_type":"type","scope":""}');
};
$token = $login->obtainToken('tmp-code', '*****');
Assert::same('hash', $token->getValue());
Assert::same('type', $token->getType());
Assert::same([], $token->getScopes());

Assert::true($login->hasToken());
Assert::type('Milo\Github\OAuth\Token', $login->getToken());

Assert::same($login, $login->dropToken());
Assert::false($login->hasToken());


# Bad security state
$storage->set('auth.state', '*****');
Assert::exception(function() use ($login) {
	$login->obtainToken('', '');
}, 'Milo\Github\OAuth\LoginException', 'OAuth security state does not match.');


# Client fails
$storage->set('auth.state', '*****');
$client->onRequest = function(Milo\Github\Http\Request $request) {
	throw new Milo\Github\Http\BadResponseException('fail');
};
$e = Assert::exception(function() use ($login) {
	$login->obtainToken('', '*****');
}, 'Milo\Github\OAuth\LoginException', 'HTTP request failed.');
$e = Assert::exception(function() use ($e) {
	throw $e->getPrevious();
}, 'Milo\Github\Http\BadResponseException', 'fail');
Assert::null($e->getPrevious());


# Invalid JSON in response
$storage->set('auth.state', '*****');
$client->onRequest = function(Milo\Github\Http\Request $request) {
	return new Milo\Github\Http\Response(200, [], '{');
};
$e = Assert::exception(function() use ($login) {
	$login->obtainToken('', '*****');
}, 'Milo\Github\OAuth\LoginException', 'Bad JSON in response.');
$e = Assert::exception(function() use ($e) {
	throw $e->getPrevious();
}, 'Milo\Github\JsonException', 'Syntax error, malformed JSON');
Assert::null($e->getPrevious());


# HTTP 404
$storage->set('auth.state', '*****');
$client->onRequest = function(Milo\Github\Http\Request $request) {
	return new Milo\Github\Http\Response(404, [], '{"error":"fail"}');
};
$e = Assert::exception(function() use ($login) {
	$login->obtainToken('', '*****');
}, 'Milo\Github\OAuth\LoginException', 'fail');
Assert::null($e->getPrevious());


# HTTP non-200 response
$storage->set('auth.state', '*****');
$client->onRequest = function(Milo\Github\Http\Request $request) {
	return new Milo\Github\Http\Response(500, [], '');
};
$e = Assert::exception(function() use ($login) {
	$login->obtainToken('', '*****');
}, 'Milo\Github\OAuth\LoginException', 'Unexpected response.');
Assert::null($e->getPrevious());
