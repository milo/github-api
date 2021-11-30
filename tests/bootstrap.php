<?php

declare(strict_types=1);

if (!is_file($autoloadFile = __DIR__ . '/../vendor/autoload.php')) {
	echo "Tester not found. Install Nette Tester using `composer update --dev`.\n";
	exit(1);
}
include $autoloadFile;
unset($autoloadFile);


Tester\Environment::setup();
date_default_timezone_set('UTC');

class Assert extends Tester\Assert
{}

function test(\closure $cb) {
	$cb();
}
