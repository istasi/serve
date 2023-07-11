<?php

declare(strict_types=1);

require_once 'test.php';

if (!showcase::$variable) {
	showcase::$variable = date('c');
}

$response->send('<h1>Hello world</h1><h4>'.date('c').'</h4><h4>'.showcase::$variable.'</h4><pre>'.showcase().'</pre>');
