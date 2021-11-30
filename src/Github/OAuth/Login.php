<?php

declare(strict_types=1);

namespace Milo\Github\OAuth;

use Milo\Github;
use Milo\Github\Http;
use Milo\Github\Storages;


/**
 * OAuth token obtaining process.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Login
{
	use Github\Strict;

	private string $authUrl = 'https://github.com/login/oauth/authorize';

	private string $tokenUrl = 'https://github.com/login/oauth/access_token';

	private Storages\SessionStorage|Storages\ISessionStorage $storage;

	private Http\IClient $client;


	public function __construct(
		private Configuration $conf,
		Storages\ISessionStorage $storage = null,
		Http\IClient $client = null
	) {
		$this->storage = $storage ?: new Storages\SessionStorage;
		$this->client = $client ?: Github\Helpers::createDefaultClient();
	}


	public function getClient(): Http\IClient
	{
		return $this->client;
	}


	/**
	 * @param  string $backUrl  URL to redirect back from GitHub when user approves the permissions request
	 * @param  ?callable $redirectCb  makes HTTP redirect to GitHub
	 */
	public function askPermissions(string $backUrl, callable $redirectCb = null): void
	{
		/** @todo Something more safe? */
		$state = sha1(uniqid((string) microtime(true), true));
		$params = [
			'client_id' => $this->conf->clientId,
			'redirect_uri' => $backUrl,
			'scope' => implode(',', $this->conf->scopes),
			'state' => $state,
		];

		$this->storage->set('auth.state', $state);

		$url = $this->authUrl . '?' . http_build_query($params);
		if ($redirectCb === null) {
			header("Location: $url");
			die();
		} else {
			call_user_func($redirectCb, $url);
		}
	}


	/**
	 * @throws LoginException
	 */
	public function obtainToken(string $code, string $state): Token
	{
		if ($state !== $this->storage->get('auth.state')) {
			throw new LoginException('OAuth security state does not match.');
		}

		$params = [
			'client_id' => $this->conf->clientId,
			'client_secret' => $this->conf->clientSecret,
			'code' => $code,
		];

		$headers = [
			'Accept' => 'application/json',
			'Content-Type' => 'application/x-www-form-urlencoded',
		];

		$request = new Http\Request(Http\Request::POST, $this->tokenUrl, $headers, http_build_query($params));
		try {
			$response = $this->client->request($request);
		} catch (Http\BadResponseException $e) {
			throw new LoginException('HTTP request failed.', 0, $e);
		}

		try {
			/** @var $json \stdClass */
			if ($response->isCode(Http\Response::S404_NOT_FOUND)) {
				$json = Github\Helpers::jsonDecode($response->getContent());
				throw new LoginException($json->error, $response->getCode());

			} elseif (!$response->isCode(Http\Response::S200_OK)) {
				throw new LoginException('Unexpected response.', $response->getCode());
			}

			$json = Github\Helpers::jsonDecode($response->getContent());

		} catch (Github\JsonException $e) {
			throw new LoginException('Bad JSON in response.', 0, $e);
		}

		$token = new Token($json->access_token, $json->token_type, strlen($json->scope) ? explode(',', $json->scope) : []);
		$this->storage->set('auth.token', $token->toArray());
		$this->storage->remove('auth.state');

		return $token;
	}


	public function hasToken(): bool
	{
		return $this->storage->get('auth.token') !== null;
	}


	/**
	 * @throws Github\LogicException  when token has not been obtained yet
	 */
	public function getToken(): Token
	{
		$token = $this->storage->get('auth.token');
		if ($token === null) {
			throw new Github\LogicException('Token has not been obtained yet.');

		} elseif ($token instanceof Token) {
			/** @deprecated */
			$token = $token->toArray();
			$this->storage->set('auth.token', $token);
		}

		return Token::createFromArray($token);
	}


	public function dropToken(): static
	{
		$this->storage->remove('auth.token');
		return $this;
	}
}
