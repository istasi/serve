<?php

declare(strict_types=1);

require_once('serve/engine.php');
$engine = new serve\engine([
    'workers' => 4
]);

$engine->on ('worker_start', function ()
{
	serve\log::entry ('Worker spawned');
});

$engine->on ('worker_end', function ()
{
	serve\log::entry ('Worker ended');
});

// Set up the thing actually doing the listening
$server = new serve\listener ( address: '0.0.0.0', port: 8000 );
$server->on ('request', function ( serve\http\response $response, serve\http\request $request )
{
	require ('site/main.php');
});
$engine->add( $server );

$engine->run ();