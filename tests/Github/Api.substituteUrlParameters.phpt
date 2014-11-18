<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class TestApi extends Milo\Github\Api
{
	public function substituteUrlParameters(& $url, array & $parameters)
	{
		return parent::substituteUrlParameters($url, $parameters);
	}
}


# substituteUrl()
test(function() {
	$api = new TestApi;

	$urls = [
		''      => ['',     ['a' => 'A', 'b' => 'B']],
		'/'     => ['/',    ['a' => 'A', 'b' => 'B']],
		':a'    => ['A',    ['b' => 'B']],
		'/:a'   => ['/A',   ['b' => 'B']],
		':a/'   => ['A/',   ['b' => 'B']],
		'/:a/'  => ['/A/',  ['b' => 'B']],
		'/:a/:b/c' => ['/A/B/c', []],
	];

	foreach ($urls as $url => $result) {
		$params = ['a' => 'A', 'b' => 'B'];
		$api->substituteUrlParameters($url, $params);

		Assert::same($url, $result[0]);
		Assert::same($params, $result[1]);
	}

	Assert::exception(function() use ($api) {
		$url = ':a';
		$params = ['A' => 'a'];
		$api->substituteUrlParameters($url, $params);
	}, 'Milo\Github\MissingParameterException', "Missing parameter 'a' for URL path ':a'.");

	Assert::exception(function() use ($api) {
		$url = ':a:b';
		$params = ['a' => 'A', 'b' => 'B'];
		$api->substituteUrlParameters($url, $params);
	}, 'Milo\Github\MissingParameterException', "Missing parameter 'a:b' for URL path ':a:b'.");
});
