<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$token = new Milo\Github\OAuth\Token('hash', 'type', ['user']);

Assert::same('hash', $token->getValue());
Assert::same('type', $token->getType());
Assert::same(['user'], $token->getScopes());
Assert::true($token->hasScope('user'));
Assert::true($token->hasScope('user:email'));
Assert::false($token->hasScope('user:foo'));
Assert::false($token->hasScope('foo'));
