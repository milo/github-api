<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class TestApi extends Milo\Github\Api
{
	public function expandUriTemplate(string $url, array $parameters, array $defaultParameters = []): string
	{
		return parent::expandUriTemplate($url, $parameters, $defaultParameters);
	}
}

$api = new TestApi;

# NULL (aka undefined)
$cases = [
	''  => '',
	'+' => '',
	'#' => '',
	'.' => '',
	'/' => '',
	';' => '',
	'?' => '',
	'&' => '',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a}", ['a' => null]));
}


# Empty
$cases = [
	''  => '',
	'+' => '',
	'#' => '#',
	'.' => '.',
	'/' => '/',
	';' => ';a',
	'?' => '?a=',
	'&' => '&a=',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a}", ['a' => '']));
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a}", ['a' => false]));
}


# Space
$cases = [
	''  => '%20',
	'+' => '%20',
	'#' => '#%20',
	'.' => '.%20',
	'/' => '/%20',
	';' => ';a=%20',
	'?' => '?a=%20',
	'&' => '&a=%20',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a}", ['a' => ' ']));
}


# Unreserved
$cases = [
	''  => '-._~',
	'+' => '-._~',
	'#' => '#-._~',
	'.' => '.-._~',
	'/' => '/-._~',
	';' => ';a=-._~',
	'?' => '?a=-._~',
	'&' => '&a=-._~',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a}", ['a' => '-._~']));
}


# Reserved
$cases = [
	''  => '%3A%2F%3F%23%5B%5D%40%21%24%26%27%28%29%2A%2B%2C%3B%3D%2533',
	'+' => ":/?#[]@!$&'()*+,;=%33",
	'#' => "#:/?#[]@!$&'()*+,;=%33",
	'.' => '.%3A%2F%3F%23%5B%5D%40%21%24%26%27%28%29%2A%2B%2C%3B%3D%2533',
	'/' => '/%3A%2F%3F%23%5B%5D%40%21%24%26%27%28%29%2A%2B%2C%3B%3D%2533',
	';' => ';a=%3A%2F%3F%23%5B%5D%40%21%24%26%27%28%29%2A%2B%2C%3B%3D%2533',
	'?' => '?a=%3A%2F%3F%23%5B%5D%40%21%24%26%27%28%29%2A%2B%2C%3B%3D%2533',
	'&' => '&a=%3A%2F%3F%23%5B%5D%40%21%24%26%27%28%29%2A%2B%2C%3B%3D%2533',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a}", ['a' => ":/?#[]@!$&'()*+,;=%33"]));
}


# Maximal length modifier
$cases = [
	''  => '12',
	'+' => '12',
	'#' => '#12',
	'.' => '.12',
	'/' => '/12',
	';' => ';a=12',
	'?' => '?a=12',
	'&' => '&a=12',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a:2}", ['a' => '123456']));
}


# Maximal length modifier with UTF-8
$cases = [
	''  => 'H%C5%AF',
	'+' => 'H%C5%AF',
	'#' => '#H%C5%AF',
	'.' => '.H%C5%AF',
	'/' => '/H%C5%AF',
	';' => ';a=H%C5%AF',
	'?' => '?a=H%C5%AF',
	'&' => '&a=H%C5%AF',
];
foreach ($cases as $operator => $expected) {
	Assert::same($expected, $api->expandUriTemplate("{{$operator}a:2}", ['a' => "H\xC5\xAFla"]));  # Hůla
}


# Examples from the RFC specification
# Level 1
test(function() use ($api) {
	$parameters = [
		'var' => 'value',
		'hello' => 'Hello World!',
	];
	$cases = [
		'{var}' => 'value',
		'{hello}' => 'Hello%20World%21',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});

# Level 2
test(function() use ($api) {
	$parameters = [
		'var' => 'value',
		'hello' => 'Hello World!',
		'path' => '/foo/bar',
	];
	$cases = [
		'{+var}' => 'value',
		'{+hello}' => 'Hello%20World!',
		'{+path}/here' => '/foo/bar/here',
		'here?ref={+path}' => 'here?ref=/foo/bar',

		'X{#var}' => 'X#value',
		'X{#hello}' => 'X#Hello%20World!',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});

# Level 3
test(function() use ($api) {
	$parameters = [
		'var' => 'value',
		'hello' => 'Hello World!',
		'empty' => '',
		'path' => '/foo/bar',
		'x' => 1024,
		'y' => 768,
	];
	$cases = [
		'map?{x,y}' => 'map?1024,768',
		'{x,hello,y}' => '1024,Hello%20World%21,768',

		'{+x,hello,y}' => '1024,Hello%20World!,768',
		'{+path,x}/here' => '/foo/bar,1024/here',

		'{#x,hello,y}' => '#1024,Hello%20World!,768',
		'{#path,x}/here' => '#/foo/bar,1024/here',

		'X{.var}' => 'X.value',
		'X{.x,y}' => 'X.1024.768',

		'{/var}' => '/value',
		'{/var,x}/here' => '/value/1024/here',

		'{;x,y}' => ';x=1024;y=768',
		'{;x,y,empty}' => ';x=1024;y=768;empty',

		'{?x,y}' => '?x=1024&y=768',
		'{?x,y,empty}' => '?x=1024&y=768&empty=',

		'?fixed=yes{&x}' => '?fixed=yes&x=1024',
		'{&x,y,empty}' => '&x=1024&y=768&empty=',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});

# Level 4
test(function() use ($api) {
	$parameters = [
		'var' => 'value',
		'hello' => 'Hello World!',
		'path' => '/foo/bar',
		'list' => ['red', 'green', 'blue'],
		'keys' => ['semi' => ';', 'dot' => '.', 'comma' => ','],
	];
	$cases = [
		'{var:3}' => 'val',
		'{var:30}' => 'value',

		'{list}' => 'red,green,blue',
		'{list*}' => 'red,green,blue',

		'{keys}' => 'semi,%3B,dot,.,comma,%2C',
		'{keys*}' => 'semi=%3B,dot=.,comma=%2C',

		'{+path:6}/here' => '/foo/b/here',
		'{+list}' => 'red,green,blue',
		'{+list*}' => 'red,green,blue',
		'{+keys}' => 'semi,;,dot,.,comma,,',
		'{+keys*}' => 'semi=;,dot=.,comma=,',

		'{#path:6}/here' => '#/foo/b/here',
		'{#list}' => '#red,green,blue',
		'{#list*}' => '#red,green,blue',
		'{#keys}' => '#semi,;,dot,.,comma,,',
		'{#keys*}' => '#semi=;,dot=.,comma=,',

		'X{.var:3}' => 'X.val',
		'X{.list}' => 'X.red,green,blue',
		'X{.list*}' => 'X.red.green.blue',
		'X{.keys}' => 'X.semi,%3B,dot,.,comma,%2C',
		'X{.keys*}' => 'X.semi=%3B.dot=..comma=%2C',

		'{/var:1,var}' => '/v/value',
		'{/list}' => '/red,green,blue',
		'{/list*}' => '/red/green/blue',
		'{/list*,path:4}' => '/red/green/blue/%2Ffoo',
		'{/keys}' => '/semi,%3B,dot,.,comma,%2C',
		'{/keys*}' => '/semi=%3B/dot=./comma=%2C',

		'{;hello:5}' => ';hello=Hello',
		'{;list}' => ';list=red,green,blue',
		'{;list*}' => ';list=red;list=green;list=blue',
		'{;keys}' => ';keys=semi,%3B,dot,.,comma,%2C',
		'{;keys*}' => ';semi=%3B;dot=.;comma=%2C',

		'{?var:3}' => '?var=val',
		'{?list}' => '?list=red,green,blue',
		'{?list*}' => '?list=red&list=green&list=blue',
		'{?keys}' => '?keys=semi,%3B,dot,.,comma,%2C',
		'{?keys*}' => '?semi=%3B&dot=.&comma=%2C',

		'{&var:3}' => '&var=val',
		'{&list}' => '&list=red,green,blue',
		'{&list*}' => '&list=red&list=green&list=blue',
		'{&keys}' => '&keys=semi,%3B,dot,.,comma,%2C',
		'{&keys*}' => '&semi=%3B&dot=.&comma=%2C',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});

# 2.4.1. Prefix Values
test(function() use ($api) {
	$parameters = [
		'var' => 'value',
		'semi' => ';',
	];
	$cases = [
		'{var}' => 'value',
		'{var:20}' => 'value',
		'{var:3}' => 'val',
		'{semi}' => '%3B',
		'{semi:2}' => '%3B',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});

# 2.4.2. Composite Values
test(function() use ($api) {
	$parameters = [
		'address' => ['city' => 'Newport Beach', 'state' => 'CA'],
		'year' => [1965, 2000, 2012],
		'dom' => ['example', 'com'],
	];
	$cases = [
		'/mapper{?address*}' => '/mapper?city=Newport%20Beach&state=CA',
		'find{?year*}' => 'find?year=1965&year=2000&year=2012',
		'www{.dom*}' => 'www.example.com',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});

# 3.2. Expression Expansion
test(function() use ($api) {
	$parameters = [
		'count' => ['one', 'two', 'three'],
		'dom' => ['example', 'com'],
		'dub' => 'me/too',
		'hello' => 'Hello World!',
		'half' => '50%',
		'var' => 'value',
		'who' => 'fred',
		'base' => 'http://example.com/home/',
		'path' => '/foo/bar',
		'list' => ['red', 'green', 'blue'],
		'keys' => ['semi' => ';', 'dot' => '.', 'comma' => ','],
		'v' => 6,
		'x' => 1024,
		'y' => 768,
		'empty' => '',
		'empty_keys' => [],
		'undef' => null,
	];
	$cases = [
		# 3.2.1. Variable Expansion
		'{count}' => 'one,two,three',
		'{count*}' => 'one,two,three',
		'{/count}' => '/one,two,three',
		'{/count*}' => '/one/two/three',
		'{;count}' => ';count=one,two,three',
		'{;count*}' => ';count=one;count=two;count=three',
		'{?count}' => '?count=one,two,three',
		'{?count*}' => '?count=one&count=two&count=three',
		'{&count*}' => '&count=one&count=two&count=three',

		# 3.2.2. Simple String Expansion: {var}
		'{var}' => 'value',
		'{hello}' => 'Hello%20World%21',
		'{half}' => '50%25',
		'O{empty}X' => 'OX',
		'O{undef}X' => 'OX',
		'{x,y}' => '1024,768',
		'{x,hello,y}' => '1024,Hello%20World%21,768',
		'?{x,empty}' => '?1024,',
		'?{x,undef}' => '?1024',
		'?{undef,y}' => '?768',
		'{var:3}' => 'val',
		'{var:30}' => 'value',
		'{list}' => 'red,green,blue',
		'{list*}' => 'red,green,blue',
		'{keys}' => 'semi,%3B,dot,.,comma,%2C',
		'{keys*}' => 'semi=%3B,dot=.,comma=%2C',

		# 3.2.3. Reserved Expansion: {+var}
		'{+var}' => 'value',
		'{+hello}' => 'Hello%20World!',
		'{+half}' => '50%25',
		'{base}index' => 'http%3A%2F%2Fexample.com%2Fhome%2Findex',
		'{+base}index' => 'http://example.com/home/index',
		'O{+empty}X' => 'OX',
		'O{+undef}X' => 'OX',
		'{+path}/here' => '/foo/bar/here',
		'here?ref={+path}' => 'here?ref=/foo/bar',
		'up{+path}{var}/here' => 'up/foo/barvalue/here',
		'{+x,hello,y}' => '1024,Hello%20World!,768',
		'{+path,x}/here' => '/foo/bar,1024/here',
		'{+path:6}/here' => '/foo/b/here',
		'{+list}' => 'red,green,blue',
		'{+list*}' => 'red,green,blue',
		'{+keys}' => 'semi,;,dot,.,comma,,',
		'{+keys*}' => 'semi=;,dot=.,comma=,',

		# 3.2.4. Fragment Expansion: {#var}
		'{#var}' => '#value',
		'{#hello}' => '#Hello%20World!',
		'{#half}' => '#50%25',
		'foo{#empty}' => 'foo#',
		'foo{#undef}' => 'foo',
		'{#x,hello,y}' => '#1024,Hello%20World!,768',
		'{#path,x}/here' => '#/foo/bar,1024/here',
		'{#path:6}/here' => '#/foo/b/here',
		'{#list}' => '#red,green,blue',
		'{#list*}' => '#red,green,blue',
		'{#keys}' => '#semi,;,dot,.,comma,,',
		'{#keys*}' => '#semi=;,dot=.,comma=,',

		# 3.2.5. Label Expansion with Dot-Prefix: {.var}
		'{.who}' => '.fred',
		'{.who,who}' => '.fred.fred',
		'{.half,who}' => '.50%25.fred',
		'www{.dom*}' => 'www.example.com',
		'X{.var}' => 'X.value',
		'X{.empty}' => 'X.',
		'X{.undef}' => 'X',
		'X{.var:3}' => 'X.val',
		'X{.list}' => 'X.red,green,blue',
		'X{.list*}' => 'X.red.green.blue',
		'X{.keys}' => 'X.semi,%3B,dot,.,comma,%2C',
		'X{.keys*}' => 'X.semi=%3B.dot=..comma=%2C',
		'X{.empty_keys}' => 'X',
		'X{.empty_keys*}' => 'X',

		# 3.2.6. Path Segment Expansion: {/var}
		'{/who}' => '/fred',
		'{/who,who}' => '/fred/fred',
		'{/half,who}' => '/50%25/fred',
		'{/who,dub}' => '/fred/me%2Ftoo',
		'{/var}' => '/value',
		'{/var,empty}' => '/value/',
		'{/var,undef}' => '/value',
		'{/var,x}/here' => '/value/1024/here',
		'{/var:1,var}' => '/v/value',
		'{/list}' => '/red,green,blue',
		'{/list*}' => '/red/green/blue',
		'{/list*,path:4}' => '/red/green/blue/%2Ffoo',
		'{/keys}' => '/semi,%3B,dot,.,comma,%2C',
		'{/keys*}' => '/semi=%3B/dot=./comma=%2C',

		# 3.2.7. Path-Style Parameter Expansion: {;var}
		'{;who}' => ';who=fred',
		'{;half}' => ';half=50%25',
		'{;empty}' => ';empty',
		'{;v,empty,who}' => ';v=6;empty;who=fred',
		'{;v,bar,who}' => ';v=6;who=fred',
		'{;x,y}' => ';x=1024;y=768',
		'{;x,y,empty}' => ';x=1024;y=768;empty',
		'{;x,y,undef}' => ';x=1024;y=768',
		'{;hello:5}' => ';hello=Hello',
		'{;list}' => ';list=red,green,blue',
		'{;list*}' => ';list=red;list=green;list=blue',
		'{;keys}' => ';keys=semi,%3B,dot,.,comma,%2C',
		'{;keys*}' => ';semi=%3B;dot=.;comma=%2C',

		# 3.2.8. Form-Style Query Expansion: {?var}
		'{?who}' => '?who=fred',
		'{?half}' => '?half=50%25',
		'{?x,y}' => '?x=1024&y=768',
		'{?x,y,empty}' => '?x=1024&y=768&empty=',
		'{?x,y,undef}' => '?x=1024&y=768',
		'{?var:3}' => '?var=val',
		'{?list}' => '?list=red,green,blue',
		'{?list*}' => '?list=red&list=green&list=blue',
		'{?keys}' => '?keys=semi,%3B,dot,.,comma,%2C',
		'{?keys*}' => '?semi=%3B&dot=.&comma=%2C',


		# 3.2.9. Form-Style Query Continuation: {&var}
		'{&who}' => '&who=fred',
		'{&half}' => '&half=50%25',
		'?fixed=yes{&x}' => '?fixed=yes&x=1024',
		'{&x,y,empty}' => '&x=1024&y=768&empty=',
		'{&x,y,undef}' => '&x=1024&y=768',
		'{&var:3}' => '&var=val',
		'{&list}' => '&list=red,green,blue',
		'{&list*}' => '&list=red&list=green&list=blue',
		'{&keys}' => '&keys=semi,%3B,dot,.,comma,%2C',
		'{&keys*}' => '&semi=%3B&dot=.&comma=%2C',
	];
	foreach ($cases as $template => $expected) {
		Assert::same($expected, $api->expandUriTemplate($template, $parameters));
	}
});


# Expanding in absolute URL
test(function () {
	$api = new Milo\Github\Api;
	$request = $api->createRequest('', 'https://host.{name}.{tld}/path/{name}', ['name' => 'milo', 'tld' => 'cz']);
	Assert::same('https://host.{name}.{tld}/path/milo', $request->getUrl());
});
