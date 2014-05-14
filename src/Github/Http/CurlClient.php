<?php

namespace Milo\Github\Http;

use Milo\Github;
use Milo\Github\Storages;


/**
 * HTTP client which use the cURL extension functions.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class CurlClient extends AbstractClient
{
	/** @var array|NULL */
	private $options;


	/**
	 * @param  array  cURL options {@link http://php.net/manual/en/function.curl-setopt.php}
	 *
	 * @throws Github\LogicException
	 */
	public function __construct(array $options = NULL)
	{
		if (!extension_loaded('curl')) {
			throw new Github\LogicException('cURL extension is not loaded.');
		}

		$this->options = $options;
	}


	/**
	 * @return Response
	 *
	 * @throws BadResponseException
	 */
	protected function process(Request $request)
	{
		$headers = [];
		foreach ($request->getHeaders() as $name => $value) {
			$headers[] = "$name: $value";
		}

		$softOptions = [
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYHOST => 0,
		];

		$hardOptions = [
			CURLOPT_FOLLOWLOCATION => FALSE, # Github sets the Location header for 201 code too and redirection is not required for us
			CURLOPT_FORBID_REUSE => TRUE,
			CURLOPT_HEADER => TRUE,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_CUSTOMREQUEST => $request->getMethod(),
			CURLOPT_URL => $request->getUrl(),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_RETURNTRANSFER => TRUE,
		];

		$curl = curl_init();
		if ($curl === FALSE) {
			throw new BadResponseException('Cannot init cURL handler.');
		}

		$result = curl_setopt_array($curl, $hardOptions + ($this->options ?: []) + $softOptions);
		if ($result === FALSE) {
			throw new BadResponseException('Setting cURL options failed: ' . curl_error($curl), curl_errno($curl));
		}

		$result = curl_exec($curl);
		if ($result === FALSE) {
			throw new BadResponseException(curl_error($curl), curl_errno($curl));
		}
		list($headersStr, $content) = explode("\r\n\r\n", $result, 2) + ['', ''];

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($code === FALSE) {
			throw new BadResponseException('HTTP status code is missing.');
		}

		$headers = [];
		foreach (array_slice(explode("\r\n", $headersStr), 1) as $header) {
			list($name, $value) = explode(': ', $header);
			$headers[$name] = $value;
		}

		return new Response($code, $headers, $content);
	}

}
