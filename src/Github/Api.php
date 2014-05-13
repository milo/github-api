<?php

namespace Milo\Github;


/**
 * Github API client library. Read readme.md in repository {@link http://github.com/milo/github-api}
 *
 * @see https://developer.github.com/v3/
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Api extends Sanity
{
	/** @var string */
	private $url = 'https://api.github.com';

	/** @var string */
	private $defaultAccept = 'application/vnd.github.v3+json';

	/** @var Http\IClient */
	private $client;

	/** @var OAuth\Token */
	private $token;


	public function __construct(Http\IClient $client = NULL)
	{
		$this->client = $client ?: new Http\StreamClient;
	}


	/**
	 * @return self
	 */
	public function setToken(OAuth\Token $token)
	{
		$this->token = $token;
		return $this;
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @param  string
	 * @return Http\Response
	 *
	 * @throws MissingParameterException
	 */
	public function delete($urlPath, array $parameters = [], array $headers = [])
	{
		return $this->request(
			$this->createRequest(Http\Request::DELETE, $urlPath, $parameters, $headers)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @param  string
	 * @return Http\Response
	 *
	 * @throws MissingParameterException
	 */
	public function get($urlPath, array $parameters = [], array $headers = [])
	{
		return $this->request(
			$this->createRequest(Http\Request::GET, $urlPath, $parameters, $headers)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @param  string
	 * @return Http\Response
	 *
	 * @throws MissingParameterException
	 */
	public function head($urlPath, array $parameters = [], array $headers = [])
	{
		return $this->request(
			$this->createRequest(Http\Request::HEAD, $urlPath, $parameters, $headers)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @param  string
	 * @param  mixed
	 * @return Http\Response
	 *
	 * @throws MissingParameterException
	 * @throws JsonException
	 */
	public function patch($urlPath, $content, array $parameters = [], array $headers = [])
	{
		return $this->request(
			$this->createRequest(Http\Request::PATCH, $urlPath, $parameters, $headers, $content)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @param  string
	 * @param  mixed
	 * @return Http\Response
	 *
	 * @throws MissingParameterException
	 * @throws JsonException
	 */
	public function post($urlPath, $content, array $parameters = [], array $headers = [])
	{
		return $this->request(
			$this->createRequest(Http\Request::POST, $urlPath, $parameters, $headers, $content)
		);
	}


	/**
	 * @see createRequest()
	 * @see request()
	 *
	 * @param  string
	 * @param  mixed
	 * @return Http\Response
	 *
	 * @throws MissingParameterException
	 * @throws JsonException
	 */
	public function put($urlPath, $content = NULL, array $parameters = [], array $headers = [])
	{
		return $this->request(
			$this->createRequest(Http\Request::PUT, $urlPath, $parameters, $headers, $content)
		);
	}


	/**
	 * @return Http\Response
	 *
	 * @throws Http\BadResponseException
	 */
	public function request(Http\Request $request)
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
	 * @param  string  Http\Request::GET|POST|...
	 * @param  string  path like '/users/:user/repos' where ':user' is substitution
	 * @param  array[name => value]  replaces substitutions in $urlPath, the rest is appended as query string to URL
	 * @param  array[name => value]  name is case-insensitive
	 * @param  mixed|NULL  arrays and objects are encoded to JSON and Content-Type is set
	 * @return Http\Request
	 *
	 * @throws MissingParameterException  when substitution is used in URL but parameter is missing
	 * @throws JsonException  when encoding to JSON fails
	 */
	public function createRequest($method, $urlPath, array $parameters = [], array $headers = [], $content = NULL)
	{
		$this->substituteUrlParameters($urlPath, $parameters);

		$url = rtrim($this->url, '/') . '/' . trim($urlPath, '/');
		if (count($parameters)) {
			$url .= '?' . http_build_query($parameters);
		}

		if ($content !== NULL && (is_array($content) || is_object($content))) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$content = Helpers::jsonEncode($content);
		}

		return new Http\Request($method, $url, $headers, $content);
	}


	/**
	 * @param  Http\Response
	 * @param  array|NULL  these codes are treated as success; code < 300 if NULL
	 * @return mixed
	 *
	 * @throws ApiException
	 */
	public function decode(Http\Response $response, array $okCodes = NULL)
	{
		$content = $response->getContent();
		if (preg_match('~application/json~i', $response->getHeader('Content-Type', ''))) {
			try {
				$content = Helpers::jsonDecode($response->getContent());
			} catch (JsonException $e) {
				throw new InvalidResponseException('JSON decoding failed.', 0, $e, $response);
			}

			if (!is_array($content) && !is_object($content)) {
				throw new InvalidResponseException('Decoded JSON is not an array or object.', 0, NULL, $response);
			}
		}

		$code = $response->getCode();
		if (($okCodes === NULL && $code >= 300) || (is_array($okCodes) && !in_array($code, $okCodes))) {
			/** @var $content \stdClass */
			switch ($code) {
				case Http\Response::S400_BAD_REQUEST:
					throw new BadRequestException($content->message, $code, NULL, $response);

				case Http\Response::S401_UNAUTHORIZED:
					throw new UnauthorizedException($content->message, $code, NULL, $response);

				case Http\Response::S403_FORBIDDEN:
					throw new ForbiddenException($content->message, $code, NULL, $response);

				case Http\Response::S404_NOT_FOUND:
					throw new NotFoundException('Resource not found or not authorized to access.', $code, NULL, $response);

				case Http\Response::S422_UNPROCESSABLE_ENTITY:
					$message = $content->message . implode(', ', array_map(function($error) {
						return '[' . implode(':', (array) $error) . ']';
					}, $content->errors));
					throw new UnprocessableEntityException($message, $code, NULL, $response);
			}

			$message = $okCodes === NULL ? '< 300' : implode(' or ', $okCodes);
			throw new UnexpectedResponseException("Expected response with code $message.", $code, NULL, $response);
		}

		return $content;
	}


	/**
	 * @return Http\IClient
	 */
	public function getClient()
	{
		return $this->client;
	}


	/**
	 * @param  string
	 * @return self
	 */
	public function setUrl($url)
	{
		$this->url = $url;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
	}


	/**
	 * @param  string
	 * @param  array
	 *
	 * @throws MissingParameterException
	 */
	protected function substituteUrlParameters(& $url, array & $parameters)
	{
		$url = preg_replace_callback('#(^|/):([^/]+)#', function($m) use ($url, & $parameters) {
			if (!isset($parameters[$m[2]])) {
				throw new MissingParameterException("Missing parameter '$m[2]' for URL path '$url'.");
			}

			$parameter = $parameters[$m[2]];
			unset($parameters[$m[2]]);
			return $m[1] . $parameter;
		}, $url);
	}

}
