<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
	$file = ltrim($class, '\\');
	$file = str_replace(
		search: '\\',
		replace: '/',
		subject: $file
	);

	$file = $file.'.php';
	$file = __DIR__.'/../'.$file;

	if (file_exists($file)) {
		require_once $file;
	}
});
