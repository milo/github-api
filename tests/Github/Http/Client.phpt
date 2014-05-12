<?php

/**
 * @author  Miloslav Hůla
 */


require __DIR__ . '/../../bootstrap.php';


class TestClient extends Milo\Github\Http\Client
{
	protected function streamRequest(Milo\Github\Http\Request $request)
	{
		return new Milo\Github\Http\Response(200, [], $request->getContent() . '+{response}');
	}
}

$client = new TestClient;

$onRequest = NULL;
$client->onRequest(function(Milo\Github\Http\Request $request) use (& $onRequest) {
	$onRequest = $request->getContent();
});

$onResponse = NULL;
$client->onResponse(function(Milo\Github\Http\Response $response) use (& $onResponse) {
	$onResponse = $response->getContent();
});

$client->request(new Milo\Github\Http\Request('', '', [], '{request}'));

Assert::same('{request}', $onRequest);
Assert::same('{request}+{response}', $onResponse);
