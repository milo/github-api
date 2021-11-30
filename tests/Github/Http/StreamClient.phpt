<?php

/**
 * @author  Miloslav Hůla
 */


require __DIR__ . '/../../bootstrap.php';


class TestClient extends Milo\Github\Http\StreamClient
{
	/** @var callable */
	public $onFileGetContents;

	protected function fileGetContents($url, array $contextOptions)
	{
		return call_user_func($this->onFileGetContents, $url, $contextOptions);
	}
}


# Common
test(function() {
	$client = new TestClient;
	$client->onFileGetContents = function($url, array $contextOptions) {
		Assert::same('http://example.com', $url);
		Assert::same([
			'http' => [
				'method' => 'METHOD',
				'header' => "custom: header\r\nexpect: \r\nconnection: close\r\n",
				'follow_location' => 0,
				'protocol_version' => 1.1,
				'ignore_errors' => true,
				'content' => '{content}',
			],
			'ssl' => [
				'verify_peer' => true,
				'cafile' => realpath(__DIR__ . '/../../../src/ca-chain.crt'),
				'disable_compression' => true,
			],
		], $contextOptions);

		return [200, ['Content-Type' => 'foo'], '{response}'];
	};

	$response = $client->request(
		new Milo\Github\Http\Request('METHOD', 'http://example.com', ['custom' => 'header'], '{content}')
	);
	Assert::same('{response}', $response->getContent());
	Assert::same(['content-type' => 'foo'], $response->getHeaders());
});


# SSL options
test(function() {
	$client = new TestClient(['option' => 'value']);
	$client->onFileGetContents = function($url, array $contextOptions) {
		Assert::type('array', $contextOptions['ssl']);
		Assert::same([
			'option' => 'value',
			'verify_peer' => true,
			'cafile' => realpath(__DIR__ . '/../../../src/ca-chain.crt'),
			'disable_compression' => true,
		], $contextOptions['ssl']);

		return [200, [], ''];
	};
	$client->request(
		new Milo\Github\Http\Request('', '', [])
	);
});


Assert::same(6, Assert::$counter);
