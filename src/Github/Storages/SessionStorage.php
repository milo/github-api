<?php

declare(strict_types=1);

namespace Milo\Github\Storages;

use Milo\Github;


/**
 * Session storage which uses $_SESSION directly. Session must be started already before use.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class SessionStorage implements ISessionStorage
{
	use Github\Strict;

	public const SESSION_KEY = 'milo.github-api';


	public function __construct(
		private string $sessionKey = self::SESSION_KEY
	) {}


	public function set(string $name, mixed $value): static
	{
		if ($value === null) {
			return $this->remove($name);
		}

		$this->check(__METHOD__);
		$_SESSION[$this->sessionKey][$name] = $value;

		return $this;
	}


	public function get(string $name): mixed
	{
		$this->check(__METHOD__);
		return $_SESSION[$this->sessionKey][$name] ?? null;
	}


	public function remove(string $name): static
	{
		$this->check(__METHOD__);
		unset($_SESSION[$this->sessionKey][$name]);
		return $this;
	}


	private function check(string $method): void
	{
		if (!isset($_SESSION)) {
			trigger_error("Start session before using $method().", E_USER_WARNING);
		}
	}
}
