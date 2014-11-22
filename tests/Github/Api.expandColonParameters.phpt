<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class TestApi extends Milo\Github\Api
{
	public function expandColonParameters($url, array $parameters, array $defaultParameters = [])
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
	];

	foreach ($urls as $url => $expected) {
		Assert::same($expected, $api->expandColonParameters($url, ['a' => 'A', 'b' => 'B']));
	}

	Assert::exception(function() use ($api) {
		$api->expandColonParameters(':a', ['A' => 'a']);
	}, 'Milo\Github\MissingParameterException', "Missing parameter 'a' for URL path ':a'.");

	Assert::exception(function() use ($api) {
		$api->expandColonParameters(':a:b', ['a' => 'A', 'b' => 'B']);
	}, 'Milo\Github\MissingParameterException', "Missing parameter 'a:b' for URL path ':a:b'.");
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
