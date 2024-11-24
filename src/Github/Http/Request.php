<?php

declare(strict_types=1);

namespace Milo\Github\Http;


/**
 * HTTP request envelope.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Request extends Message
{
	/** HTTP request method */
	public const
		DELETE = 'DELETE',
		GET = 'GET',
		HEAD = 'HEAD',
		PATCH = 'PATCH',
		POST = 'POST',
		PUT = 'PUT';


	public function __construct(
		private string $method,
		private string $url,
		array $headers = [],
		?string $content = null
	) {
		parent::__construct($headers, $content);
	}


	public function isMethod(string $method): bool
	{
		return strcasecmp($this->method, $method) === 0;
	}


	public function getMethod(): string
	{
		return $this->method;
	}


	public function getUrl(): string
	{
		return $this->url;
	}


	public function addHeader(string $name, ?string $value): static
	{
		return parent::addHeader($name, $value);
	}


	public function setHeader(string $name, ?string $value): static
	{
		return parent::setHeader($name, $value);
	}
}
