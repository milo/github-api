<?php

declare(strict_types=1);

namespace Milo\Github\Http;


/**
 * HTTP client interface.
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
interface IClient
{
	/**
	 * @return Response
	 */
	function request(Request $request);

	/**
	 * @param  callable|null
	 * @return self
	 */
	function onRequest($callback);

	/**
	 * @param  callable|null
	 * @return self
	 */
	function onResponse($callback);
}
