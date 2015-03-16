[Github API](https://developer.github.com/v3/) easy access library. Works with cURL, stream or any of your HTTP client.

[![Build Status](https://travis-ci.org/milo/github-api.svg?branch=master)](https://travis-ci.org/milo/github-api)
[![Downloads last 30 days](https://img.shields.io/packagist/dm/milo/github-api.svg)](https://packagist.org/packages/milo/github-api)


# Installation
Download [release](https://github.com/milo/github-api/releases), decompress and include `github-api.php` manually, or use [Composer](https://getcomposer.org/):
```
composer require milo/github-api
```


# Documentation
Everything at [wiki pages](https://github.com/milo/github-api/wiki), including the [short classes description](https://github.com/milo/github-api/wiki/Classes-description).


Quick Start
===========
List all emojis used on Github.
```php
use Milo\Github;

$api = new Github\Api;
$response = $api->get('/emojis');
$emojis = $api->decode($response);

print_r($emojis);
```

```
stdClass Object (
    [+1] => https://github.global.ssl.fastly.net/images/icons/emoji/+1.png?v5
    [-1] => https://github.global.ssl.fastly.net/images/icons/emoji/-1.png?v5
    [100] => https://github.global.ssl.fastly.net/images/icons/emoji/100.png?v5
    [1234] => https://github.global.ssl.fastly.net/images/icons/emoji/1234.png?v5
    [8ball] => https://github.global.ssl.fastly.net/images/icons/emoji/8ball.png?v5
    [a] => https://github.global.ssl.fastly.net/images/icons/emoji/a.png?v5
    ...
)
```


# License
The MIT License (MIT)

Copyright (c) 2014 Miloslav Hůla

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
