<?php

declare(strict_types=1);

namespace serve\engine;

use serve\log;
use serve\traits;
use serve\exceptions\kill;

class client extends base
{
	use traits\setup;

	public function __construct()
	{
		parent::__construct(size: -1);

		$this->options = [];
	}

	public function __destruct()
	{
		foreach ($this as $connection) {
			$connection->close();
		}
	}

	public function run()
	{
		do {
			/**
			 * No idea why this matters, but having this here, seem to make sure that the engine\client are consistently killed off when engine\server is killed (SIGTERM/SIGINT)
			 */
			pcntl_signal_dispatch();

			$read = $this->streams();
			$write = $this->streams(function ($connection) {
				return true === $connection->write;
			});
			$except = [];

			$changes = @stream_select(read: $read, write: $write, except: $except, seconds: $this->options['internal_delay'], microseconds: 0);
			if ($changes === false) {
				break;
			}

			if ($changes < 1) {
				continue;
			}

			foreach ($read as $connection) {
				$connection = $this->fromStream($connection);

				try {
					$message = $connection->read();
					if (false === empty($message)) {
						log::entry($message);
					}
				} catch (kill $e) {
					foreach ($this as $connection) {
						$connection->close();
					}

					break 2;
				}
			}

			foreach ($write as $connection) {
				$connection = $this->fromStream($connection);
				if (null !== $connection) {
					$connection->write();
				}
			}
		} while (1);
	}
}
