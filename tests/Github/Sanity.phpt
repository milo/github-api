<?php

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


class TestSanity extends Milo\Github\Sanity
{}

Assert::exception(function() {
	(new TestSanity)->undefined;
}, 'Milo\Github\LogicException', 'Cannot read an undeclared property TestSanity::$undefined.');

Assert::exception(function() {
	$mock = new TestSanity;
	$mock->undefined = '';
}, 'Milo\Github\LogicException', 'Cannot write to an undeclared property TestSanity::$undefined.');
