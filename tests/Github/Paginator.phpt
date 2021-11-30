<?php

/**
 * @author  Miloslav Hůla
 *
 * @testCase
 */

require __DIR__ . '/../bootstrap.php';


class MockApi extends Milo\Github\Api
{
	/** @var callable */
	public $onRequest;

	/** @return Milo\Github\Http\Response */
	public function request(Milo\Github\Http\Request $request)
	{
		return call_user_func($this->onRequest, $request);
	}
}


class PaginatorTestCase extends Tester\TestCase
{
	/** @var MockApi */
	private $api;

	public function setUp()
	{
		$this->api = new MockApi;
	}


	public function testBasics()
	{
		$responses = [
			$r1 = new Milo\Github\Http\Response(200, ['Link' => '<url://test?page=2>; rel="next"'], 'page-1'),
			$r2 = new Milo\Github\Http\Response(200, ['Link' => '<url://test?page=3>; rel="next"'], 'page-2'),
			$r3 = new Milo\Github\Http\Response(200, [], 'page-3'),
			$r4 = new Milo\Github\Http\Response(200, [], 'page-4'),
		];

		$paginator = new Milo\Github\Paginator($this->api, new Milo\Github\Http\Request(
			'METHOD',
			'url://test'
		));

		$requests = [];
		$this->api->onRequest = function(Milo\Github\Http\Request $request) use (&$requests, &$responses) {
			$requests[] = $request;
			return array_shift($responses);
		};

		$keys = $values = [];
		foreach ($paginator as $k => $v) {
			$keys[] = $k;
			$values[] = $v;
		}

		Assert::same([$r1, $r2, $r3], $values);
		Assert::same([1, 2, 3], $keys);

		Assert::same(3, count($requests));
		Assert::same('url://test', $requests[0]->getUrl());
		Assert::same('url://test?page=2', $requests[1]->getUrl());
		Assert::same('url://test?page=3', $requests[2]->getUrl());
		Assert::same('METHOD', $requests[0]->getMethod());
		Assert::same('METHOD', $requests[1]->getMethod());
		Assert::same('METHOD', $requests[2]->getMethod());
	}


	public function testParsePage()
	{
		Assert::same(1, Milo\Github\Paginator::parsePage('url://test?page=1'));
		Assert::same(2, Milo\Github\Paginator::parsePage('url://test?page=2'));

		Assert::same(1, Milo\Github\Paginator::parsePage('url://test'));
		Assert::same(1, Milo\Github\Paginator::parsePage('url://test?page='));
		Assert::same(1, Milo\Github\Paginator::parsePage('url://test?page=0'));
		Assert::same(1, Milo\Github\Paginator::parsePage('url://test?page=foo'));
	}


	public function testParseLink()
	{
		Assert::same('url://test', Milo\Github\Paginator::parseLink('<url://test>; rel="foo"', 'foo'));
		Assert::same('url://test', Milo\Github\Paginator::parseLink('<url://test>;rel="foo"', 'foo'));
		Assert::same('url://test', Milo\Github\Paginator::parseLink("<url://test>;\r\n\trel=\"foo\"", 'foo'));
		Assert::same('url://test', Milo\Github\Paginator::parseLink("foo\n<url://test>; rel=\"foo\"", 'foo'));

		$link = '<url://a>; rel="a",'."\n\t".'<url://b>; rel="b",'."\n\t".'<url://c>; rel="c"';
		Assert::same('url://a', Milo\Github\Paginator::parseLink($link, 'a'));
		Assert::same('url://b', Milo\Github\Paginator::parseLink($link, 'b'));
		Assert::same('url://c', Milo\Github\Paginator::parseLink($link, 'c'));

		Assert::same(null, Milo\Github\Paginator::parseLink('', ''));
		Assert::same(null, Milo\Github\Paginator::parseLink('<url://test>; rel="foo"', 'bar'));
	}


	public function testLimit()
	{
		$responses = [
			$r1 = new Milo\Github\Http\Response(200, ['Link' => '<url://test?page=21>; rel="next"'], 'page-20'),
			$r2 = new Milo\Github\Http\Response(200, ['Link' => '<url://test?page=22>; rel="next"'], 'page-21'),
			$r3 = new Milo\Github\Http\Response(200, [], 'page-22'),
		];

		$paginator = new Milo\Github\Paginator($this->api, new Milo\Github\Http\Request(
			'METHOD',
			'url://test?page=20'
		));

		$this->api->onRequest = function(Milo\Github\Http\Request $request) use (&$requests, &$stack) {
			$requests[] = $request;
			return array_shift($stack);
		};


		$requests = $values = [];
		$stack = $responses;
		foreach ($paginator->limit(1) as $v) {
			$values[] = $v;
		}
		Assert::same([$r1], $values);
		Assert::same(1, count($requests));


		$requests = $values = [];
		$stack = $responses;
		foreach ($paginator->limit(2) as $v) {
			$values[] = $v;
		}
		Assert::same([$r1, $r2], $values);
		Assert::same(2, count($requests));


		$requests = $values = [];
		$stack = $responses;
		foreach ($paginator->limit(0) as $v) {
			$values[] = $v;
		}
		Assert::same([], $values);
		Assert::same(0, count($requests));
	}

}

(new PaginatorTestCase())->run();
