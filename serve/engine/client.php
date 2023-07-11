<?php

declare(strict_types=1);

namespace serve\engine;

use serve\log;
use serve\traits;

class client extends base
{
	use traits\setup;

	public function __construct()
	{
		parent::__construct(size: -1);

		$this->options = [];
	}

	public function run()
	{
		do {
			$read = $this->streams();
			$write = $this->streams(function ($connection) {
				return true === $connection->write;
			});
			$except = [];

			$changes = stream_select(read: $read, write: $write, except: $except, seconds: $this->options['internal_delay'], microseconds: 0);
			if ($changes < 1) {
				continue;
			}

			foreach ($read as $stream) {
				$connection = $this->fromStream($stream);

				$message = $connection->read();
				if (false !== $message) {
					log::entry($message);
				}
			}
		} while (1);
	}
}
