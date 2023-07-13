<?php

declare(strict_types=1);

namespace serve\engine;

use serve\connections\listener;
use serve\log;
use serve\traits;
use serve\exceptions\kill;
use serve\interfaces;

class client extends base implements interfaces\setup
{
	use traits\setup;

	public function __construct()
	{
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
		$that = $this;
		foreach ($this->getIterator() as $connection) {
			if (($connection instanceof listener) === false) {
				continue;
			}

			$connection->on('accept', function ($connection) use ($that) {
				$that->add($connection);
			});
		}

		do {
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

		foreach ($this as $connection) {
			$connection->close();
		}
	}
}
