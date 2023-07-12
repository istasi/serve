<?php

declare(strict_types=1);

namespace serve\connections\engine\trait;

if (trait_exists('getTime') === true) {
	return;
}

trait getTime
{
	public function getTime(string $file): int
	{
		static $files = [];

		if (isset($files [$file]) === false) {
			$files [$file]= [0,0];
		}

		if ($files [$file][0] < time()) {
			$files [$file][0] = time();
			$files [$file][1] = filemtime($file);
		}

		return $files [$file][1];
	}
}