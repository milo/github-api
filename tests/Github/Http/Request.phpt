<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../../bootstrap.php';


$request = new Milo\Github\Http\Request('Foo', 'http://');

Assert::same('Foo', $request->getMethod());
Assert::true($request->isMethod('Foo'));
Assert::true($request->isMethod('FOO'));

Assert::same('http://', $request->getUrl());

# methods publicity
Assert::same($request, $request->addHeader('foo', 'bar'));
Assert::same($request, $request->setHeader('foo', 'bar'));
