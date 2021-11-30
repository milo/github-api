<?php

declare(strict_types=1);

namespace Milo\Github;


/**
 * Iterates through the GitHub API responses by Link: header.
 *
 * @see https://developer.github.com/guides/traversing-with-pagination/
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Paginator implements \Iterator
{
	use Strict;

	private Http\Request $firstRequest;

	private ?Http\Request $request;

	private ?Http\Response $response;

	private ?int $limit = null;

	private int $counter = 0;


	public function __construct(
		private Api $api,
		Http\Request $request
	) {
		$this->firstRequest = clone $request;
	}


	/**
	 * Limits maximum steps of iteration.
	 */
	public function limit(?int $limit): static
	{
		$this->limit = $limit;
		return $this;
	}


	public function rewind(): void
	{
		$this->request = $this->firstRequest;
		$this->response = null;
		$this->counter = 0;
	}


	public function valid(): bool
	{
		return $this->request !== null && ($this->limit === null || $this->counter < $this->limit);
	}


	public function current(): Http\Response
	{
		$this->load();
		return $this->response;
	}


	public function key(): int
	{
		return static::parsePage($this->request->getUrl());
	}


	public function next(): void
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


	private function load(): void
	{
		if ($this->response === null) {
			$this->response = $this->api->request($this->request);
		}
	}


	public static function parsePage(string $url): int
	{
		[, $parametersStr] = explode('?', $url, 2) + ['', ''];
		parse_str($parametersStr, $parameters);
		return max((int) ($parameters['page'] ?? 1), 1);
	}


	/**
	 * @see  https://developer.github.com/guides/traversing-with-pagination/#navigating-through-the-pages
	 */
	public static function parseLink(string $link, string $rel): ?string
	{
		if (!preg_match('(<([^>]+)>;\s*rel="' . preg_quote($rel) . '")', $link, $match)) {
			return null;
		}
		return $match[1];
	}
}
