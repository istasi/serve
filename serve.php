<?php

declare(strict_types=1);

require_once 'serve/autoload.php';

$server = new serve\connections\http\ssl\listener([
	'address' => '0.0.0.0',
	'port' => 8000,
	'ssl' => [
		'local_cert' => '/home/istasi/php/server-cert.pem',
		'local_pk' => '/home/istasi/php/server-key.pem',
		'allow_self_signed' => true,
		'verify_peer' => false,
		'verify_peer_name' => false,
		'disable_compression' => true
	]
]);

$server->on('request', function (serve\http\response $response, serve\http\request $request) {
	serve\log::entry ( $request->server ['remote_addr'] .': '. $request->server ['request_uri'] );

	require 'site/main.php';
});

$engine = new serve\engine([
	'workers' => 4,
]);
$engine->add($server);

$engine->run();
