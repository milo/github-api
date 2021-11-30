<?php

/**
 * @author  Miloslav Hůla
 */


require __DIR__ . '/../../bootstrap.php';


class TestClient extends Milo\Github\Http\AbstractClient
{
	/** @var callable */
	public $onProcess;

	protected function process(Milo\Github\Http\Request $request)
	{
		return call_user_func($this->onProcess, $request);
	}
}


# Redirecting
test(function() {
	$responses = [
		new Milo\Github\Http\Response(301, ['Location' => ''], ''),
		new Milo\Github\Http\Response(302, ['Location' => ''], ''),
		new Milo\Github\Http\Response(307, ['Location' => ''], ''),
		new Milo\Github\Http\Response(201, ['Location' => ''], ''),
		new Milo\Github\Http\Response(200, ['Location' => ''], ''),
	];

	$client = new TestClient;
	$client->onProcess = function (Milo\Github\Http\Request $request) use (&$responses) {
		return array_shift($responses);
	};

	$request = new Milo\Github\Http\Request('', '', [], '');
	$response = $client->request($request);

	Assert::same(201, $response->getCode());
	$response = $response->getPrevious();
	Assert::same(307, $response->getCode());
	$response = $response->getPrevious();
	Assert::same(302, $response->getCode());
	$response = $response->getPrevious();
	Assert::same(301, $response->getCode());

	Assert::null($response->getPrevious());

	Assert::same(1, count($responses));
});


# onRequest(), onResponse()
test(function() {
	$client = new TestClient;
	$client->onProcess = function() { return new Milo\Github\Http\Response(200, [], '{response}'); };

	$onRequest = null;
	$client->onRequest(function(Milo\Github\Http\Request $request) use (&$onRequest) {
		$onRequest = $request->getContent();
	});

	$onResponse = null;
	$client->onResponse(function(Milo\Github\Http\Response $response) use (&$onResponse) {
		$onResponse = $response->getContent();
	});

	$client->request(new Milo\Github\Http\Request('', '', [], '{request}'));

	Assert::same('{request}', $onRequest);
	Assert::same('{response}', $onResponse);
});


# Additional headers
test(function(){
	$client = new TestClient;
	$client->onProcess = function(Milo\Github\Http\Request $request) {
		Assert::same([
			'expect' => '',
		], $request->getHeaders());

		return new Milo\Github\Http\Response(200, [], '');
	};
	$client->request(new Milo\Github\Http\Request('', '', []));
});


Assert::same(9, Assert::$counter);
