<?php

/**
 * @author  Miloslav Hůla
 */


require __DIR__ . '/../../bootstrap.php';


class TestClient extends Milo\Github\Http\StreamClient
{
	/** @var callable */
	public $onProcess;

	protected function process(Milo\Github\Http\Request $request)
	{
		return call_user_func($this->onProcess, $request);
	}
}

$client = new TestClient;

$responses = [
	new Milo\Github\Http\Response(301, ['Location' => ''], ''),
	new Milo\Github\Http\Response(302, ['Location' => ''], ''),
	new Milo\Github\Http\Response(307, ['Location' => ''], ''),
	new Milo\Github\Http\Response(201, ['Location' => ''], ''),
	new Milo\Github\Http\Response(200, ['Location' => ''], ''),
];

$client->onProcess = function (Milo\Github\Http\Request $request) use (& $responses) {
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
