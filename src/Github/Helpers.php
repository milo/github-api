<?php

declare(strict_types=1);

namespace Milo\Github;


/**
 * Just helpers.
 *
 * The JSON encode/decode methods are stolen from Nette Utils (https://github.com/nette/utils).
 *
 * @author  David Grudl
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class Helpers
{
	private static Http\IClient $client;


	/**
	 * @throws JsonException
	 */
	public static function jsonEncode(mixed $value): string
	{
		$json = json_encode($value, JSON_UNESCAPED_UNICODE);
		if ($error = json_last_error()) {
			throw new JsonException(json_last_error_msg(), $error);
		}
		return $json;
	}


	/**
	 * @throws JsonException
	 */
	public static function jsonDecode(string $json): mixed
	{
		$value = json_decode($json, false, 512, JSON_BIGINT_AS_STRING);
		if ($error = json_last_error()) {
			throw new JsonException(json_last_error_msg(), $error);
		}
		return $value;
	}


	public static function createDefaultClient(bool $newInstance = false): Http\IClient
	{
		if (empty(self::$client) || $newInstance) {
			self::$client = extension_loaded('curl')
				? new Http\CurlClient
				: new Http\StreamClient;
		}

		return self::$client;
	}
}
