<?php

declare(strict_types=1);

namespace Milo\Github\OAuth;

use Milo\Github;


/**
 * OAuth token envelope.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Token
{
	use Github\Strict;


	/**
	 * @param  string[] $scopes
	 */
	public function __construct(
		private string $value,
		private string $type = '',
		private array $scopes = []
	) {}


	public function getValue(): string
	{
		return $this->value;
	}


	public function getType(): string
	{
		return $this->type;
	}


	/**
	 * @return string[]
	 */
	public function getScopes(): array
	{
		return $this->scopes;
	}


	/**
	 * @see https://developer.github.com/v3/oauth/#scopes
	 */
	public function hasScope(string $scope): bool
	{
		if (in_array($scope, $this->scopes, true)) {
			return true;
		}

		static $superiors = [
			'user:email' => 'user',
			'user:follow' => 'user',
			'notifications' => 'repo',
		];

		if (array_key_exists($scope, $superiors) && in_array($superiors[$scope], $this->scopes, true)) {
			return true;
		}

		return false;
	}


	/** @internal */
	public function toArray(): array
	{
		return [
			'value' => $this->value,
			'type' => $this->type,
			'scopes' => $this->scopes,
		];
	}


	/** @internal */
	public static function createFromArray(array $data): static
	{
		return new static($data['value'], $data['type'], $data['scopes']);
	}
}
