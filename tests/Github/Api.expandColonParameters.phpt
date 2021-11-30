<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class TestApi extends Milo\Github\Api
{
	public function expandColonParameters(string $url, array $parameters, array $defaultParameters = []): string
	{
		return parent::expandColonParameters($url, $parameters, $defaultParameters);
	}
}


# URL parameters like :name
test(function() {
	$api = new TestApi;

	$urls = [
		'' => '?a=A&b=B',
		'/' => '?a=A&b=B',
		':a' => 'A?b=B',
		'/:a' => '/A?b=B',
		':a/' => 'A?b=B',
		'/:a/' => '/A?b=B',
		'/:a/:b/c' => '/A/B/c',
		'/:a.:b/c' => '/A.B/c',
		'/:a...:b/c' => '/A...B/c',
	];

	foreach ($urls as $url => $expected) {
		Assert::same($expected, $api->expandColonParameters($url, ['a' => 'A', 'b' => 'B']));
	}

	Assert::exception(function() use ($api) {
		$api->expandColonParameters(':a', ['A' => 'a']);
	}, Milo\Github\MissingParameterException::class, "Missing parameter 'a' for URL path ':a'.");

	Assert::exception(function() use ($api) {
		$api->expandColonParameters(':a:b', ['a' => 'A', 'b' => 'B']);
	}, Milo\Github\MissingParameterException::class, "Missing parameter 'a:b' for URL path ':a:b'.");
});


# Parameters escaping
test(function() {
	$api = new TestApi;

	Assert::same('/with%20space', $api->expandColonParameters('/:name', ['name' => 'with space']));
});


# Default parameters expanding
test(function() {
	$api = new TestApi;

	Assert::same('/default', $api->expandColonParameters('/:var', [], ['var' => 'default']));
	Assert::same('/set', $api->expandColonParameters('/:var', ['var' => 'set'], ['var' => 'default']));
});


# Expanding in absolute URL
test(function () {
	$api = new Milo\Github\Api;
	$request = $api->createRequest('', 'https://host.:name.:tld/path/:name', ['name' => 'milo', 'tld' => 'cz']);
	Assert::same('https://host.:name.:tld/path/milo?tld=cz', $request->getUrl());
});
