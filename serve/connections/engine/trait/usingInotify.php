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
		static $inotify = null;
		static $files = [];
		static $ids = [];

		if ($inotify === null) {
			$inotify = inotify_init();
		}

		if (isset($files [$file]) === false) {
			$id = inotify_add_watch($inotify, $file, IN_MODIFY);
			$ids [ $id ] = $file;
			$files [$file]= [time(), filemtime($file)];
		}

		if (inotify_queue_len($inotify) > 0) {
			$events = inotify_read($inotify);
			if ($events) {
				var_dump($events);
				foreach ($events as $event) {
					if (isset($event ['wd']) === true && isset($ids [ $event ['wd'] ]) === true) {
						$file = $ids [ $event ['wd'] ];
						$files [ $file ][1] = filemtime($file);
					}
				}
			}
		}

		return $files [$file][1];
	}
}
