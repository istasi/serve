<?php

declare(strict_types=1);


spl_autoload_register(function ($class) {
	$file = ltrim($class, '\\');
	$file = str_replace(
		search: '\\',
		replace: '/',
		subject: $file
	);

	$file = realpath (__DIR__.'/../serve/'.$file.'.php');

	if ($file !== false && file_exists($file)) {
		require_once $file;
	}
});
