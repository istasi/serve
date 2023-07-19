<?php

declare(strict_types=1);

namespace serve\traits\engine;

if (function_exists('inotify_init') === true) {
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
} else {
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
}
