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
