<?php

declare(strict_types=1);

namespace serve\engine;

use serve\connections;
use serve\connections\unix\client;
use serve\engine;
use serve\log;
use serve\threads\thread;
use serve\traits;

class server extends base
{
	use traits\setup;

	public function __construct(array $options = [])
	{
		parent::__construct(size: -1);

		$this->options = [
			'workers' => 4,
			'internal_delay' => 1,
		];

		$this->setup($options);
	}

	public function run(): void
	{
		$workers = $this->options['workers'];

		do {
			$read = $this->streams(function ($connection) {
				return $connection instanceof client;
			});

			if (count($read) < $workers) {
				$this->spawn($workers - count($read));

				$read = $this->streams(function ($connection) {
					return $connection instanceof client;
				});
			}

			$write = $this->streams(function ($connection) {
				return true === $connection->write;
			});

			$changes = stream_select(read: $read, write: $write, except: $except, seconds: $this->options['internal_delay'], microseconds: 0);
			if ($changes < 1) {
				continue;
			}

			foreach ($read as $connection) {
				$connection = $this->fromStream($connection);
				if (null === $connection) {
					continue;
				}

				$message = $connection->read();
				if ($message) {
					log::entry($message);
				}
			}
		} while (1);

		log::entry('ran out of connections');
	}

	public function spawn(int $amount): void
	{
		$original = $this;

		for ($i = 0; $i < $amount; ++$i) {
			$client = thread::spawn(function ($server) use ($original) {
				$engine = new engine\client();
				$engine->setup($original->setup());
				$engine->add($server);

				foreach ($original as $connection) {
					if ($connection instanceof connections\listener) {
						$engine->add($connection);
					}
				}

				$engine->run();
			});

			$this->add($client);
		}
	}
}
