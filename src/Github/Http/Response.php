<?php

declare(strict_types=1);

namespace Milo\Github\Http;

use Milo\Github;


/**
 * HTTP response envelope.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Response extends Message
{
	/** HTTP 1.1 code */
	public const
		S200_OK = 200,
		S301_MOVED_PERMANENTLY = 301,
		S302_FOUND = 302,
		S304_NOT_MODIFIED = 304,
		S307_TEMPORARY_REDIRECT = 307,
		S400_BAD_REQUEST = 400,
		S401_UNAUTHORIZED = 401,
		S403_FORBIDDEN = 403,
		S404_NOT_FOUND = 404,
		S422_UNPROCESSABLE_ENTITY = 422;

	private ?Response $previous = null;


	public function __construct(
		private int $code,
		array $headers,
		?string $content,
	) {
		parent::__construct($headers, $content);
	}


	/**
	 * HTTP response status code.
	 */
	public function getCode(): int
	{
		return $this->code;
	}


	public function isCode(int $code): bool
	{
		return $this->code === $code;
	}


	public function getPrevious(): ?Response
	{
		return $this->previous;
	}


	/**
	 * @throws Github\LogicException
	 */
	public function setPrevious(?Response $previous = null): static
	{
		if ($this->previous) {
			throw new Github\LogicException('Previous response is already set.');
		}
		$this->previous = $previous;

		return $this;
	}
}
