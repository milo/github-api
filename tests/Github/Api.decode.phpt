<?php

declare(strict_types=1);

/**
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';


$api = new Milo\Github\Api;

# No JSON
$response = new Milo\Github\Http\Response(200, [], 'foo');
Assert::same('foo', $api->decode($response));

# JSON
$response = new Milo\Github\Http\Response(200, ['Content-Type' => 'application/json'], '[]');
Assert::same([], $api->decode($response));


# Invalid JSON
$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(200, ['Content-Type' => 'application/json'], '[');
	$api->decode($response);
}, Milo\Github\InvalidResponseException::class, 'JSON decoding failed.');
$e = Assert::exception(function() use ($e) {
	throw $e->getPrevious();
}, Milo\Github\JsonException::class);
Assert::null($e->getPrevious());


# Invalid JSON
$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(200, ['Content-Type' => 'application/json'], '""');
	$api->decode($response);
}, Milo\Github\InvalidResponseException::class, 'Decoded JSON is not an array or object.');
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(400, ['Content-Type' => 'application/json'], '{"message":"error"}');
	$api->decode($response);
}, Milo\Github\BadRequestException::class, 'error', 400);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(401, ['Content-Type' => 'application/json'], '{"message":"error"}');
	$api->decode($response);
}, Milo\Github\UnauthorizedException::class, 'error', 401);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(403, ['Content-Type' => 'application/json'], '{"message":"error"}');
	$api->decode($response);
}, Milo\Github\ForbiddenException::class, 'error', 403);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(403, ['Content-Type' => 'application/json', 'X-RateLimit-Remaining' => '0'], '{"message":"error"}');
	$api->decode($response);
}, Milo\Github\RateLimitExceedException::class, 'error', 403);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(404, [], '');
	$api->decode($response);
}, Milo\Github\NotFoundException::class, 'Resource not found or not authorized to access.', 404);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(422, ['Content-Type' => 'application/json'], '{"message":"error"}');
	$api->decode($response);
}, Milo\Github\UnprocessableEntityException::class, 'error', 422);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(422, ['Content-Type' => 'application/json'], '{"message":"error", "errors":[{"a":"b","c":"d"}]}');
	$api->decode($response);
}, Milo\Github\UnprocessableEntityException::class, 'error[b:d]', 422);
Assert::null($e->getPrevious());


$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(500, [], '');
	$api->decode($response);
}, Milo\Github\UnexpectedResponseException::class, 'Expected response with code < 300.', 500);
Assert::null($e->getPrevious());


$response = new Milo\Github\Http\Response(500, [], 'foo');
Assert::same('foo', $api->decode($response, [500]));

$e = Assert::exception(function() use ($api) {
	$response = new Milo\Github\Http\Response(200, [], '');
	$api->decode($response, [201, 304]);
}, Milo\Github\UnexpectedResponseException::class, 'Expected response with code 201 or 304.', 200);
Assert::null($e->getPrevious());
