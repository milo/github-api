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
	/** @var Http\IClient */
	private static $client;


	/**
	 * @param  mixed
	 * @return string
	 *
	 * @throws JsonException
	 */
	public static function jsonEncode($value)
	{
		$json = json_encode($value, JSON_UNESCAPED_UNICODE);
		if ($error = json_last_error()) {
			throw new JsonException(json_last_error_msg(), $error);
		}
		return $json;
	}


	/**
	 * @param  mixed
	 * @return string
	 *
	 * @throws JsonException
	 */
	public static function jsonDecode($json)
	{
		$value = json_decode((string) $json, false, 512, JSON_BIGINT_AS_STRING);
		if ($error = json_last_error()) {
			throw new JsonException(json_last_error_msg(), $error);
		}
		return $value;
	}


	/**
	 * @param  bool
	 * @return Http\IClient
	 */
	public static function createDefaultClient($newInstance = false)
	{
		if (self::$client === null || $newInstance) {
			self::$client = extension_loaded('curl')
				? new Http\CurlClient
				: new Http\StreamClient;
		}

		return self::$client;
	}
}
