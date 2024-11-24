<?php

declare(strict_types=1);

namespace Milo\Github;


/**
 * Github API client library. Read readme.md in repository {@link http://github.com/milo/github-api}
 *
 * @see https://developer.github.com/v3/
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Api
{
	use Strict;

	private string $url = 'https://api.github.com';

	private string $defaultAccept = 'application/vnd.github.v3+json';

	private ?array $defaultParameters = [];

	private Http\IClient $client;

	private ?OAuth\Token $token = null;


	public function __construct(?Http\IClient $client = null)
	{
		$this->client = $client ?: Helpers::createDefaultClient();
	}


	public function setToken(?OAuth\Token $token = null): static
	{
		$this->token = $token;
		return $this;
	}


	public function getToken(): ?OAuth\Token
	{
		return $this->token;
	}


	public function setDefaultParameters(?array $defaults = null): static
	{
		$this->defaultParameters = $defaults ?: [];
		return $this;
	}


	public function getDefaultParameters(): array
	{
		return $this->defaultParameters;
	}


	public function setDefaultAccept(string $accept): self
	{
		$this->defaultAccept = $accept;
		return $this;
	}


	public function getDefaultAccept(): string
	{
		return $this->defaultAccept;
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @throws MissingParameterException
	 */
	public function delete(string $urlPath, array $parameters = [], array $headers = []): Http\Response
	{
		return $this->request(
			$this->createRequest(Http\Request::DELETE, $urlPath, $parameters, $headers)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @throws MissingParameterException
	 */
	public function get(string $urlPath, array $parameters = [], array $headers = []): Http\Response
	{
		return $this->request(
			$this->createRequest(Http\Request::GET, $urlPath, $parameters, $headers)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @throws MissingParameterException
	 */
	public function head(string $urlPath, array $parameters = [], array $headers = []): Http\Response
	{
		return $this->request(
			$this->createRequest(Http\Request::HEAD, $urlPath, $parameters, $headers)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @throws MissingParameterException
	 * @throws JsonException
	 */
	public function patch(string $urlPath, string|array|object|null $content, array $parameters = [], array $headers = []): Http\Response
	{
		return $this->request(
			$this->createRequest(Http\Request::PATCH, $urlPath, $parameters, $headers, $content)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @throws MissingParameterException
	 * @throws JsonException
	 */
	public function post(string $urlPath, string|array|object|null $content, array $parameters = [], array $headers = []): Http\Response
	{
		return $this->request(
			$this->createRequest(Http\Request::POST, $urlPath, $parameters, $headers, $content)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @throws MissingParameterException
	 * @throws JsonException
	 */
	public function put(string $urlPath, string|array|object|null $content = null, array $parameters = [], array $headers = []): Http\Response
	{
		return $this->request(
			$this->createRequest(Http\Request::PUT, $urlPath, $parameters, $headers, $content)
		);
	}


	/**
	 * @throws Http\BadResponseException
	 */
	public function request(Http\Request $request): Http\Response
	{
		$request = clone $request;

		$request->addHeader('Accept', $this->defaultAccept);
		$request->addHeader('Time-Zone', date_default_timezone_get());
		$request->addHeader('User-Agent', 'milo/github-api');

		if ($this->token) {
			/** @todo Distinguish token type? */
			$request->addHeader('Authorization', "token {$this->token->getValue()}");
		}

		return $this->client->request($request);
	}


	/**
	 * @param  string $method  Http\Request::GET|POST|...
	 * @param  string $urlPath  like '/users/:user/repos' where ':user' is substitution
	 * @param  string[] $parameters  replaces substitutions in $urlPath, the rest is appended as query string to URL
	 * @param  string[] $headers  key as case-insensitive header name
	 * @param  string|array|object|null $content  arrays and objects are encoded to JSON and Content-Type is set
	 *
	 * @throws MissingParameterException  when substitution is used in URL but parameter is missing
	 * @throws JsonException  when encoding to JSON fails
	 */
	public function createRequest(string $method, string $urlPath, array $parameters = [], array $headers = [], string|array|object|null $content = null): Http\Request
	{
		if (stripos($urlPath, $this->url) === 0) {  # Allows non-HTTPS URLs
			$baseUrl = $this->url;
			$urlPath = substr($urlPath, strlen($this->url));

		} elseif (preg_match('#^(https://[^/]+)(/.*)?$#', $urlPath, $m)) {
			$baseUrl = $m[1];
			$urlPath = $m[2] ?? '';

		} else {
			$baseUrl = $this->url;
		}

		if (!str_contains($urlPath, '{')) {
			$urlPath = $this->expandColonParameters($urlPath, $parameters, $this->defaultParameters);
		} else {
			$urlPath = $this->expandUriTemplate($urlPath, $parameters, $this->defaultParameters);
		}

		$url = rtrim($baseUrl, '/') . '/' . ltrim($urlPath, '/');

		if (is_array($content) || is_object($content)) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$content = Helpers::jsonEncode($content);
		}

		return new Http\Request($method, $url, $headers, $content);
	}


	/**
	 * @param  ?int[] $okCodes  these codes are treated as success; code < 300 if null
	 *
	 * @throws ApiException
	 */
	public function decode(Http\Response $response, ?array $okCodes = null): mixed
	{
		$content = $response->getContent();
		if (preg_match('~application/json~i', $response->getHeader('Content-Type', ''))) {
			try {
				$content = Helpers::jsonDecode($response->getContent());
			} catch (JsonException $e) {
				throw new InvalidResponseException('JSON decoding failed.', 0, $e, $response);
			}

			if (!is_array($content) && !is_object($content)) {
				throw new InvalidResponseException('Decoded JSON is not an array or object.', 0, null, $response);
			}
		}

		$code = $response->getCode();
		if (($okCodes === null && $code >= 300) || (is_array($okCodes) && !in_array($code, $okCodes))) {
			/** @var $content \stdClass */
			switch ($code) {
				case Http\Response::S400_BAD_REQUEST:
					throw new BadRequestException(self::errorMessage($content), $code, null, $response);

				case Http\Response::S401_UNAUTHORIZED:
					throw new UnauthorizedException(self::errorMessage($content), $code, null, $response);

				case Http\Response::S403_FORBIDDEN:
					if ($response->getHeader('X-RateLimit-Remaining') === '0') {
						throw new RateLimitExceedException(self::errorMessage($content), $code, null, $response);
					}
					throw new ForbiddenException(self::errorMessage($content), $code, null, $response);

				case Http\Response::S404_NOT_FOUND:
					throw new NotFoundException('Resource not found or not authorized to access.', $code, null, $response);

				case Http\Response::S422_UNPROCESSABLE_ENTITY:
					throw new UnprocessableEntityException(self::errorMessage($content), $code, null, $response);
			}

			$message = $okCodes === null ? '< 300' : implode(' or ', $okCodes);
			throw new UnexpectedResponseException("Expected response with code $message.", $code, null, $response);
		}

		return $content;
	}


	/**
	 * Creates paginator for HTTP GET requests.
	 *
	 * @see get()
	 *
	 * @throws MissingParameterException
	 */
	public function paginator(string $urlPath, array $parameters = [], array $headers = []): Paginator
	{
		return new Paginator(
			$this,
			$this->createRequest(Http\Request::GET, $urlPath, $parameters, $headers)
		);
	}


	public function getClient(): Http\IClient
	{
		return $this->client;
	}


	public function withUrl(string $url): Api
	{
		$api = clone $this;
		$api->setUrl($url);
		return $api;
	}


	public function setUrl(string $url): static
	{
		$this->url = $url;
		return $this;
	}


	public function getUrl(): string
	{
		return $this->url;
	}


	/**
	 * @throws MissingParameterException
	 */
	protected function expandColonParameters(string $url, array $parameters, array $defaultParameters): string
	{
		$parameters += $defaultParameters;

		$url = preg_replace_callback('#(^|/|\.):([^/.]+)#', function($m) use ($url, &$parameters) {
			if (!isset($parameters[$m[2]])) {
				throw new MissingParameterException("Missing parameter '$m[2]' for URL path '$url'.");
			}
			$parameter = $parameters[$m[2]];
			unset($parameters[$m[2]]);
			return $m[1] . rawurlencode($parameter);
		}, $url);

		$url = rtrim($url, '/');

		if (count($parameters)) {
			$url .= '?' . http_build_query($parameters);
		}

		return $url;
	}


	/**
	 * Expands URI template (RFC 6570).
	 *
	 * @see http://tools.ietf.org/html/rfc6570
	 * @todo Inject remaining default parameters into query string?
	 */
	protected function expandUriTemplate(string $url, array $parameters, array $defaultParameters): string
	{
		$parameters += $defaultParameters;

		static $operatorFlags = [
			''  => ['prefix' => '',  'separator' => ',', 'named' => false, 'ifEmpty' => '',  'reserved' => false],
			'+' => ['prefix' => '',  'separator' => ',', 'named' => false, 'ifEmpty' => '',  'reserved' => true],
			'#' => ['prefix' => '#', 'separator' => ',', 'named' => false, 'ifEmpty' => '',  'reserved' => true],
			'.' => ['prefix' => '.', 'separator' => '.', 'named' => false, 'ifEmpty' => '',  'reserved' => false],
			'/' => ['prefix' => '/', 'separator' => '/', 'named' => false, 'ifEmpty' => '',  'reserved' => false],
			';' => ['prefix' => ';', 'separator' => ';', 'named' => true,  'ifEmpty' => '',  'reserved' => false],
			'?' => ['prefix' => '?', 'separator' => '&', 'named' => true,  'ifEmpty' => '=', 'reserved' => false],
			'&' => ['prefix' => '&', 'separator' => '&', 'named' => true,  'ifEmpty' => '=', 'reserved' => false],
		];

		return preg_replace_callback('~{([+#./;?&])?([^}]+?)}~', function($m) use ($url, &$parameters, $operatorFlags) {
			$flags = $operatorFlags[$m[1]];

			$translated = [];
			foreach (explode(',', $m[2]) as $name) {
				$explode = false;
				$maxLength = null;
				if (preg_match('~^(.+)(?:(\*)|:(\d+))$~', $name, $tmp)) { // TODO: Speed up?
					$name = $tmp[1];
					if (isset($tmp[3])) {
						$maxLength = (int) $tmp[3];
					} else {
						$explode = true;
					}
				}

				if (!isset($parameters[$name])) {  // TODO: Throw exception?
					continue;
				}

				$value = $parameters[$name];
				if (is_scalar($value)) {
					$translated[] = $this->prefix($flags, $name, $this->escape($flags, $value, $maxLength));

				} else {
					$value = (array) $value;
					$isAssoc = key($value) !== 0;

					// The '*' (explode) modifier
					$parts = [];
					if ($explode) {
						if ($isAssoc) {
							$this->walk($value, function ($v, $k) use (&$parts, $flags, $maxLength) {
								$parts[] = $this->prefix(['named' => true] + $flags, $k, $this->escape($flags, $v, $maxLength));
							});

						} elseif ($flags['named']) {
							$this->walk($value, function ($v) use (&$parts, $flags, $name, $maxLength) {
								$parts[] = $this->prefix($flags, $name, $this->escape($flags, $v, $maxLength));
							});

						} else {
							$this->walk($value, function ($v) use (&$parts, $flags, $maxLength) {
								$parts[] = $this->escape($flags, $v, $maxLength);
							});
						}

						if (isset($parts[0])) {
							if ($flags['named']) {
								$translated[] = implode($flags['separator'], $parts);
							} else {
								$translated[] = $this->prefix($flags, $name, implode($flags['separator'], $parts));
							}
						}

					} else {
						$this->walk($value, function($v, $k) use (&$parts, $isAssoc, $flags, $maxLength) {
							if ($isAssoc) {
								$parts[] = $this->escape($flags, $k);
							}

							$parts[] = $this->escape($flags, $v, $maxLength);
						});

						if (isset($parts[0])) {
							$translated[] = $this->prefix($flags, $name, implode(',', $parts));
						}
					}
				}
			}

			if (isset($translated[0])) {
				return $flags['prefix'] . implode($flags['separator'], $translated);
			}

			return '';
		}, $url);
	}


	/**
	 * @param  string $value  already escaped
	 */
	private function prefix(array $flags, string $name, string $value): string
	{
		$prefix = '';
		if ($flags['named']) {
			$prefix .= $this->escape($flags, $name);
			if (isset($value[0])) {
				$prefix .= '=';
			} else {
				$prefix .= $flags['ifEmpty'];
			}
		}

		return $prefix . $value;
	}


	private function escape(array $flags, string|int|false $value, ?int $maxLength = null): string
	{
		$value = (string) $value;

		if ($maxLength !== null) {
			if (preg_match('~^(.{' . $maxLength . '}).~u', $value, $m)) {
				$value = $m[1];
			} elseif (strlen($value) > $maxLength) {  # when malformed UTF-8
				$value = substr($value, 0, $maxLength);
			}
		}

		if ($flags['reserved']) {
			$parts = preg_split('~(%[0-9a-fA-F]{2}|[:/?#[\]@!$&\'()*+,;=])~', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
			$parts[] = '';

			$escaped = '';
			for ($i = 0, $count = count($parts); $i < $count; $i += 2) {
				$escaped .= rawurlencode($parts[$i]) . $parts[$i + 1];
			}

			return $escaped;
		}

		return rawurlencode($value);
	}


	private function walk(array $array, callable $cb): void
	{
		foreach ($array as $k => $v) {
			if ($v === null) {
				continue;
			}

			$cb($v, $k);
		}
	}


	private static function errorMessage(\stdClass $content): string
	{
		$message = $content->message ?? 'Unknown error';

		if (isset($content->errors)) {
			$message .= implode(', ', array_map(function($error) {
				return '[' . implode(':', (array) $error) . ']';
			}, $content->errors));
		}

		return $message;
	}
}
