<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

$app = new \Slim\App;
$app->get('/hello/{start}/{end}', function (Request $request, Response $response) {

	$start = $request->getAttribute('start');
    $end = $request->getAttribute('end');
	$date = '03/04/2017';

	$extractor = new BursaExtractor($date, $date, $start, $end);
	$output = $extractor->run();

    $data = $response->withJson(json_decode($output), 200);

    return $data;
});
$app->run();

