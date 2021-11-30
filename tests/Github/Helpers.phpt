<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


$client1 = Milo\Github\Helpers::createDefaultClient();
Assert::type('Milo\Github\Http\IClient', $client1);

$client2 = Milo\Github\Helpers::createDefaultClient();
Assert::type('Milo\Github\Http\IClient', $client2);

Assert::same($client1, $client2);


$client3 = Milo\Github\Helpers::createDefaultClient(true);
Assert::type('Milo\Github\Http\IClient', $client3);

Assert::notSame($client1, $client3);
Assert::notSame($client2, $client3);
