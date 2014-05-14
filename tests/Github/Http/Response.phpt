<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$response = new Milo\Github\Http\Response('200', [], '');

Assert::same(200, $response->getCode());
Assert::true($response->isCode(200));
Assert::true($response->isCode('200'));
Assert::false($response->isCode(0));


# Previous
$response = new Milo\Github\Http\Response('200', [], '1');
$previous = new Milo\Github\Http\Response('200', [], '2');
Assert::null($response->getPrevious());

$response->setPrevious($previous);
Assert::same($previous, $response->getPrevious());

Assert::exception(function() use ($response, $previous) {
	$response->setPrevious($previous);
}, 'Milo\Github\LogicException', 'Previous response is already set.');
