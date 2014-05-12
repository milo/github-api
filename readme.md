Github API Client
=================
This library helps to work with [Github API](https://developer.github.com/v3/). An implemented [HTTP client](https://github.com/milo/github-api/blob/master/src/Github/Http/Client.php) works with `cURL` or `stream_get_contents()`.

Todo:
- cURL
- FileCache


Quick Start
===========
A following short example makes request for list of emojis used on Github.
```php
use Milo\Github;

$api = new Github\Api;
$response = $api->get('/emojis');
$emojis = $api->decode($response);

var_dump($emojis);
```


Obtaining OAuth token
=====================
For many requests you need an authorization. Class [Milo\Github\OAuth\Login](https://github.com/milo/github-api/blob/master/src/Github/OAuth/Login.php) helps you to obtain an OAuth token.

The workflow is described in [Github API documentation](https://developer.github.com/v3/oauth/). In short:
- you redirect user to Github web page with authorization request
- after agree-click, he/she will be redirected back to your application with temporary code in URL
- you send a POST request to obtain a token
- now you have a token

So browser is redirected to Github web page and back. Login class needs to store some security information and session is needed.

At first, you must register your application at Github web site (Account Settings -> Applications -> Developer applications). You obtain `$clientId` and `$clientSecret`. These you need to obtain the token.
```php
use Milo\Github;

session_start();

# Scopes for token
$config = new Github\OAuth\Config($clientId, $clientSecret, ['user', 'repo']);

# Default implementation of ISessionStorage
$storage = new Github\Storages\SessionStorage;

$login = new Github\OAuth\Login($config, $storage);

# Token obtaining
if ($login->hasToken()) {
	$token = $login->getToken();
} else {
	if (!isset($_GET['back'])) {
		# Following redirects to Github web page.
		$login->askPermissions('https://application.example.com/index.php?back=1');

	} else {
		$token = $login->obtainToken($_GET['code'], $_GET{'state'});

		# maybe redirect here to drop the parameters from URL but it is up to you
	}
```

Method `Login::askPermissions()` performs a redirection by `header("Location: $url")` by default. Your application may use an own mechanisms:
```php
$login->askPermissions('https://my.application.example/index.php?back=1', function($url) use ($myFramework) {
	$myFramework->redirect($url);
});
```

The token is stored by Login class. If you store it by your own, drop it explicitly.
```php
$login->dropToken();
```

Check out the `Login::__construct()` for all optional parameters.


Api access
==========
Few examples are more then thousands words.
```php
use Milo\Github;

$api = new Github\Api;
$api->setToken($token);

$response = $api->get('/users/:user/repos', [  # Do you see the :user substring? That's I call substitution.
	'user' => 'milo',
	'type' => 'owner'
]);

# Same as above but without substitution.
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

# Which token scopes token has and which I need to access this API part?
$response = $api->head('/user/followers');
var_dump( $response->getHead('X-OAuth-Scopes') );           # Now I have.
var_dump( $response->getHead('X-Accepted-OAuth-Scopes') );  # These I need.

# Send own HTTP headers
$response = $api->get('/repos/:owner/:repo/issues/comments', [
	'owner' => 'nette',
	'repo' => 'tracy',
], [
	'Accept' => 'application/vnd.github.v3.full+json',
]);
```

Do you need a lower level access? Check out the [Milo\Github\HTTP\Request](https://github.com/milo/github-api/blob/master/src/Github/Http/Request.php) at first.
```php
use Milo\Github;

# Store this by imaginary Github API /cool/service?user=milo
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
	'PUT', 'https://api.github.com/cool/service?user=milo',
	['Content-Type' => 'application/json'],
	json_encode($data)
);

# Send it
$response = $api->request($request);
```

And what about response? Check out the [Milo\Github\HTTP\Response](https://github.com/milo/github-api/blob/master/src/Github/Http/Response.php).
```php
use Milo\Github;

$response = $api->request($request);

if (!$response->isCode(200)) {
	throw new \RuntimeException('Bad response.', $response->getCode());
}

var_dump( $response->getHeader('Content-Type') );
$json = $response->getContent();
$data = json_decode($json);


# Or easily use Api::decode(). It checks many things for you.
try {
	$data = $api->decode($response, [200, 201]);
} catch (Github\ApiException $e) {
}
```


HTTP client
===========
This library contains HTTP client implementation [Milo\Github\Http\Client](https://github.com/milo/github-api/blob/master/src/Github/Http/Client.php). It uses `cURL` if available with `stream_get_contents()` fallback. If you have an own sweet one, implement the bridge with [Milo\Github\Http\IClient](https://github.com/milo/github-api/blob/master/src/Github/Http/IClient.php) interface.

If you read the Github API documentation already, you noted the requests [rate limiting](https://developer.github.com/v3/#rate-limiting). This is a good reason to cache an HTTP requests:
```php
use Milo\Github;

$cache = new Github\Storages\FileCache(__DIR__ . '/temp');
$client = new Github\Http\Client($cache);
$api = new Github\Api($client);
```

Sometimes is good to know what the HTTP client does.
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
