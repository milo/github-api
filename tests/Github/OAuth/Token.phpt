<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$token = new Milo\Github\OAuth\Token('hash1');

Assert::same('hash1', $token->getValue());
Assert::same('', $token->getType());
Assert::same([], $token->getScopes());


$token = new Milo\Github\OAuth\Token('hash2', 'type', ['user']);

Assert::same('hash2', $token->getValue());
Assert::same('type', $token->getType());
Assert::same(['user'], $token->getScopes());
Assert::true($token->hasScope('user'));
Assert::true($token->hasScope('user:email'));
Assert::false($token->hasScope('user:foo'));
Assert::false($token->hasScope('foo'));

$asArray = [
	'value' => 'hash2',
	'type' => 'type',
	'scopes' => ['user'],
];
Assert::same($asArray, $token->toArray());

$fromArray = Milo\Github\OAuth\Token::createFromArray($asArray);
Assert::equal($token, $fromArray);
