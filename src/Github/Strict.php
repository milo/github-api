<?php

declare(strict_types=1);

namespace Milo\Github;


/**
 * Undefined member access check. Stolen from Nette (https://nette.org).
 */
trait Strict
{
	/** @throws LogicException */
	public function &__get($name)
	{
		throw new LogicException('Cannot read an undeclared property ' . get_class($this) . "::\$$name.");
	}


	/** @throws LogicException */
	public function __set($name, $value)
	{
		throw new LogicException('Cannot write to an undeclared property ' . get_class($this) . "::\$$name.");
	}
}
