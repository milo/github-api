<?php

declare(strict_types=1);

namespace Milo\Github;


/**
 * Iterates through the Github API responses by Link: header.
 *
 * @see https://developer.github.com/guides/traversing-with-pagination/
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Paginator implements \Iterator
{
	use Strict;

	/** @var Api */
	private $api;

	/** @var Http\Request */
	private $firstRequest;

	/** @var Http\Request|null */
	private $request;

	/** @var Http\Response|null */
	private $response;

	/** @var int */
	private $limit;

	/** @var int */
	private $counter = 0;


	public function __construct(Api $api, Http\Request $request)
	{
		$this->api = $api;
		$this->firstRequest = clone $request;
	}


	/**
	 * Limits maximum steps of iteration.
	 *
	 * @param  int|null
	 * @return self
	 */
	public function limit($limit)
	{
		$this->limit = $limit === null
			? null
			: (int) $limit;

		return $this;
	}


	/**
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function rewind()
	{
		$this->request = $this->firstRequest;
		$this->response = null;
		$this->counter = 0;
	}


	/**
	 * @return bool
	 */
	#[\ReturnTypeWillChange]
	public function valid()
	{
		return $this->request !== null && ($this->limit === null || $this->counter < $this->limit);
	}


	/**
	 * @return Http\Response
	 */
	#[\ReturnTypeWillChange]
	public function current()
	{
		$this->load();
		return $this->response;
	}


	/**
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	public function key()
	{
		return static::parsePage($this->request->getUrl());
	}


	/**
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function next()
	{
		$this->load();

		if ($url = static::parseLink((string) $this->response->getHeader('Link'), 'next')) {
			$this->request = new Http\Request(
				$this->request->getMethod(),
				$url,
				$this->request->getHeaders(),
				$this->request->getContent()
			);
		} else {
			$this->request = null;
		}

		$this->response = null;
		$this->counter++;
	}


	private function load()
	{
		if ($this->response === null) {
			$this->response = $this->api->request($this->request);
		}
	}


	/**
	 * @param  string
	 * @return int
	 */
	public static function parsePage($url)
	{
		list (, $parametersStr) = explode('?', $url, 2) + ['', ''];
		parse_str($parametersStr, $parameters);

		return isset($parameters['page'])
			? max(1, (int) $parameters['page'])
			: 1;
	}


	/**
	 * @see  https://developer.github.com/guides/traversing-with-pagination/#navigating-through-the-pages
	 *
	 * @param  string
	 * @param  string
	 * @return string|null
	 */
	public static function parseLink($link, $rel)
	{
		if (!preg_match('(<([^>]+)>;\s*rel="' . preg_quote($rel) . '")', $link, $match)) {
			return null;
		}

		return $match[1];
	}
}
