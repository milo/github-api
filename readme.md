[Github API](https://developer.github.com/v3/) easy access library. Works with cURL, stream or any of your HTTP client.

[![Build Status](https://travis-ci.org/milo/github-api.png?branch=master)](https://travis-ci.org/milo/github-api)

Todo
----
- HTTP\CurlClient
- pagination helpers


Installation
============
Download [release](https://github.com/milo/github-api/releases), decompress and include `github-api.php` manually, or use [Composer](https://getcomposer.org/):
```
composer require milo/github-api
```


Quick Start
===========
List all emojis used on Github.
```php
use Milo\Github;

$api = new Github\Api;
$response = $api->get('/emojis');
$emojis = $api->decode($response);

print_r($emojis);
/*
stdClass Object
(
    [+1] => https://github.global.ssl.fastly.net/images/icons/emoji/+1.png?v5
    [-1] => https://github.global.ssl.fastly.net/images/icons/emoji/-1.png?v5
    [100] => https://github.global.ssl.fastly.net/images/icons/emoji/100.png?v5
    [1234] => https://github.global.ssl.fastly.net/images/icons/emoji/1234.png?v5
    [8ball] => https://github.global.ssl.fastly.net/images/icons/emoji/8ball.png?v5
    [a] => https://github.global.ssl.fastly.net/images/icons/emoji/a.png?v5
    ...
)
*/
```


OAuth token
===========
You need an authorization for many request. [Milo\Github\OAuth\Login](https://github.com/milo/github-api/blob/master/src/Github/OAuth/Login.php) helps you to obtain an OAuth token. The workflow is described in [Github API documentation](https://developer.github.com/v3/oauth/)

At first, register your application at Github web site (Account Settings -> Applications -> Developer applications). You obtain a `$clientId` and `$clientSecret`. These you need to obtain the token.
```php
use Milo\Github;

session_start();

$config = new Github\OAuth\Config($clientId, $clientSecret, ['user', 'repo']);
$storage = new Github\Storages\SessionStorage;  # default naive implementation
$login = new Github\OAuth\Login($config, $storage);

# Your application URL
$appUrl = 'https://my.application.tld/index.php';

# Token obtaining
if ($login->hasToken()) {
	$token = $login->getToken();

} else {
	if (isset($_GET['back'])) {
		$token = $login->obtainToken($_GET['code'], $_GET['state']);
		header("Location: $appUrl");  # drop the 'code' and 'state' from URL
		die();

	} else {
		# Performs redirect to Github page and die().
		# Pass own redirection callback as 2nd argument if you need.
		$login->askPermissions("$appUrl?back=1");
	}
```

The token is stored by Login class in SessionStorage. Drop it if you need:
```php
$login->dropToken();
```


Api access
==========
Few examples are more then thousands words.
```php
use Milo\Github;

$api = new Github\Api;

# Not necessary for non-authorized access
$api->setToken($token);

# The :user substring I call a substitution.
$response = $api->get('/users/:user/repos', [
	'user' => 'milo',
	'type' => 'owner'
]);

# Same as above but without the substitution.
$response = $api->get('/users/milo/repos', [
	'type' => 'owner'
]);

# Upload private GIST
$gist = [
	'description' => 'API test',
	'private' => TRUE,
	'files' => [
		'test1.txt' => [
			'content' => 'This is a milo/github-api test ' . time(),
		],
		'test2.txt' => [
			'content' => 'This is a milo/github-api test ' . time(),
		],
	],
];
$response = $api->post('/gists', $gists);

# Info about required scopes /user/followers service
$response = $api->head('/user/followers');
var_dump( $response->getHead('X-OAuth-Scopes') );           # Now I have.
var_dump( $response->getHead('X-Accepted-OAuth-Scopes') );  # These I need.

# Send own HTTP headers
$response = $api->get('/repos/:owner/:repo/issues/comments', ['owner'=>'nette', 'repo'=>'tracy'], [
	'Accept' => 'application/vnd.github.v3.full+json',
]);
```

Lower level access possible by [Milo\Github\HTTP\Request](https://github.com/milo/github-api/blob/master/src/Github/Http/Request.php).
```php
use Milo\Github;

# Imaginary data for imaginary API service
$data = ['one' => '1', 'two' => '3'];

# Low-level
$request = $api->createRequest(
	Github\Http\Request::PUT,
	'/cool/service',
	['user' => 'milo'],
	[],
	$data
);

# Low-low-level
$request = $api->createRequest(
	Github\Http\Request::PUT,
	'/cool/service',
	['user' => 'milo'],
	['Content-Type' => 'application/json'],
	json_encode($data)
);

# Low-low-low-level
$request = new Github\Http\Request(
	'PUT',
	'https://api.github.com/cool/service?user=milo',
	['Content-Type' => 'application/json'],
	json_encode($data)
);

# Send it
$response = $api->request($request);
```

Check out the [Milo\Github\HTTP\Response](https://github.com/milo/github-api/blob/master/src/Github/Http/Response.php).
```php
use Milo\Github;

$response = $api->request($request);

if (!$response->isCode(200)) {
	throw new \RuntimeException('Bad response.', $response->getCode());
}

var_dump( $response->getHeader('Content-Type') );
$json = $response->getContent();
$data = json_decode($json);


# Api::decode() checks many things for you.
try {
	$data = $api->decode($response, [200, 201]);
} catch (Github\ApiException $e) {
}
```


HTTP client
===========
[cURL](https://github.com/milo/github-api/blob/master/src/Github/Http/CurlClient.php) and [stream](https://github.com/milo/github-api/blob/master/src/Github/Http/StreamClient.php) clients for your needs or implement the bridge with [Milo\Github\Http\IClient](https://github.com/milo/github-api/blob/master/src/Github/Http/IClient.php) interface.

Rate limiting
-------------
[Rate limiting](https://developer.github.com/v3/#rate-limiting) limits amount of request in time. It is a good reason to cache requests:
```php
use Milo\Github;

$cache = new Github\Storages\FileCache(__DIR__ . '/temp');  # default naive implementation
$client = new Github\Http\CachedClient($cache);

$api = new Github\Api($client);
```

Debugging
---------
```php
$client = $api->getClient();

$client->onRequest(function(Github\Http\Request $request) {
	var_dump($request);
});

$client->onResponse(function(Github\Http\Response $response) {
	var_dump($response);
});
```


Exceptions
==========
All at one place in [exceptions.php](https://github.com/milo/github-api/blob/master/src/Github/exceptions.php).

All exceptions throws by this library implements a marker `Milo\Github\IException.php`. See the exception classes doc blocks for their purpose.


License
=======
The MIT License (MIT)

Copyright (c) 2014 Miloslav Hůla

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL SIMON TATHAM BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
