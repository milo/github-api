<?php

declare(strict_types=1);

namespace Milo\Github\Http;

use Milo\Github;


/**
 * HTTP request or response ascendant.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
abstract class Message
{
	use Github\Strict;

	private array $headers = [];


	public function __construct(
		array $headers = [],
		private ?string $content = null,
	) {
		foreach ($headers as $name => $value) {
			$this->setHeader($name, $value);
		}
	}


	public function hasHeader(string $name): bool
	{
		return array_key_exists(strtolower($name), $this->headers);
	}


	public function getHeader(string $name, ?string $default = null): ?string
	{
		$name = strtolower($name);
		return array_key_exists($name, $this->headers)
			? $this->headers[$name]
			: $default;
	}


	protected function addHeader(string $name, ?string $value): static
	{
		$name = strtolower($name);
		if (!array_key_exists($name, $this->headers) && $value !== null) {
			$this->headers[$name] = $value;
		}

		return $this;
	}


	protected function setHeader(string $name, ?string $value): static
	{
		$name = strtolower($name);
		if ($value === null) {
			unset($this->headers[$name]);
		} else {
			$this->headers[$name] = $value;
		}

		return $this;
	}


	/**
	 * @return string[]
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}


	public function getContent(): ?string
	{
		return $this->content;
	}
}
