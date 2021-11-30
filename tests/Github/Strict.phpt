<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class TestStrict
{
	use Milo\Github\Strict;
}

Assert::exception(function() {
	(new TestStrict)->undefined;
}, Milo\Github\LogicException::class, 'Cannot read an undeclared property TestStrict::$undefined.');

Assert::exception(function() {
	$mock = new TestStrict;
	$mock->undefined = '';
}, Milo\Github\LogicException::class, 'Cannot write to an undeclared property TestStrict::$undefined.');
