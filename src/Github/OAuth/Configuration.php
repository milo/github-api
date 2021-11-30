<?php

declare(strict_types=1);

namespace Milo\Github\OAuth;

use Milo\Github;


/**
 * Configuration for OAuth token obtaining.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Configuration
{
	use Github\Strict;


	/**
	 * @param  string[] $scopes
	 */
	public function __construct(
		public string $clientId,
		public string $clientSecret,
		public array $scopes = [],
	) {}


	public static function fromArray(array $conf): static
	{
		return new static(
			$conf['clientId'],
			$conf['clientSecret'],
			$conf['scopes'] ?? [],
		);
	}
}
