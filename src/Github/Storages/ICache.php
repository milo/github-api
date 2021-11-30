<?php

namespace Milo\Github\Storages;


interface ICache
{
	/**
	 * @param  string
	 * @param  mixed
	 * @return mixed  stored value
	 */
	function save($key, $value);


	/**
	 * @param  string
	 * @return mixed|null
	 */
	function load($key);
}
