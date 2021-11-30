<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$config = new Milo\Github\OAuth\Configuration('id', 'secret', ['s', 'c']);
Assert::same('id', $config->clientId);
Assert::same('secret', $config->clientSecret);
Assert::same(['s', 'c'], $config->scopes);


$config = Milo\Github\OAuth\Configuration::fromArray([
	'clientId' => 'id2',
	'clientSecret' => 'secret2',
	'scopes' => ['o', 'p'],
]);
Assert::same('id2', $config->clientId);
Assert::same('secret2', $config->clientSecret);
Assert::same(['o', 'p'], $config->scopes);
