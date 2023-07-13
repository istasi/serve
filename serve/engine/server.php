<?php

declare(strict_types=1);

namespace serve\engine;

use Exception;
use serve\connections;
use serve\connections\engine\client;
use serve\engine;
use serve\log;
use serve\threads\thread;
use serve\traits;
use serve\interfaces;

class server extends base implements interfaces\setup
{
	use traits\setup;

	public function __construct(array $options = [])
	{
		$this->options = [
			'workers' => 4,
			'internal_delay' => 1,
		];

		$this->setup($options);
	}

	public function __destruct()
	{
		foreach ($this as $connection) {
			$connection->close();
		}
	}

	public function run(): void
	{
		$defaultPool = new engine\pool([
			'workers' => $this->options ['workers']
		]);

		/** @var engine\pool[] $pools */
		$pools = [];

		foreach ($this->getIterator() as $connection) {
			if ($connection instanceof interfaces\setup) {
				$options = $connection->setup();

				if (isset($options ['pool']) === false || ($options ['pool'] instanceof engine\pool) === false) {
					$options ['pool'] = $defaultPool;
				}

				/** @var engine\pool $pool */
				$pool = $options ['pool'];
				if (isset($pools [ $pool->id() ]) === false) {
					$pools [ $pool->id() ] = $pool;
				}

				$pool->add($connection);
			}
		}

		/**
		 * We want it to stop executing the script a place that fit, such as stream_socket_select, from where we can attempt to do a more graceful shutdown
		 * If a child process is getting hit by a SIGTERM instead, this process will just spawn a new one. Im not sure as to whenever
		 */
		pcntl_async_signals(false);

		/**
		 * Not sure why, but the function supplied to pcntl_signal doesn't seem to be actually run
		 * But this with the above, allows us to control where we die, $change = stream_socket_select (); will return false if we are being killed.
		 *
		 * Note: These get executed when die ()/exit () are called, exit (0); does not seem to make it a clean exit in terms of pcntl_wifexited ()
		 */
		/*
		pcntl_signal(SIGTERM, function () { exit(0); });
		pcntl_signal(SIGINT, function () {});
		*/

		do {
			$read = [];
			foreach ($pools as $pool) {
				$workers = $pool->get('workers');

				$pool->streams(function ($connection) use (&$workers, &$read) {
					if ($connection instanceof client) {
						$workers--;

						$read [] = $connection->stream;
					}
				});

				if ($workers > 0) {
					$connections = [];
					foreach ($pool->getIterator() as $connection) {
						if (($connection instanceof client) === false) {
							$connections [] = $connection;
						}
					}

					foreach ($this->spawn($workers, $connections) as $connection) {
						$pool->add($connection);
					}

					$read = array_merge($read, $pool->streams(function ($connection) {
						return $connection instanceof client;
					}));
				}
			}
			$read = array_unique($read);

			$write = $this->streams(function ($connection) {
				return true === $connection->write;
			});

			$changes = @stream_select(read: $read, write: $write, except: $except, seconds: $this->options['internal_delay'], microseconds: 0);
			if ($changes === false) {
				break;
			}

			if ($changes < 1) {
				continue;
			}

			foreach ($read as $connection) {
				$connection = $this->fromStream($connection);
				if (null === $connection) {
					continue;
				}

				$message = $connection->read();
				if (empty($message) === false) {
					log::entry($message);
				}
			}

			foreach ($write as $connection) {
				$connection = $this->fromStream($connection);
				if (null === $connection) {
					continue;
				}

				$connection->write();
			}
		} while (1);

		thread::killall();
	}

	/**
	 *
	 * @param int $amount
	 * @param connections\base[] $connections
	 * @return connections\engine\client[]
	 * @throws Exception
	 */
	public function spawn(int $amount, array $connections = []): array
	{
		/** @var connections\engine\client[] $clients */
		$clients = [];

		for ($i = 0; $i < $amount; ++$i) {
			$clients [] = thread::spawn(function ($server) use ($connections) {
				/** @var connections\base[] $connections */

				$engine = new engine\client();
				$engine->add($server);

				foreach ($connections as $connection) {
					$connection->trigger('worker_start');

					$engine->add($connection);
				}

				$engine->run();

				exit(0);
			});
		}

		return $clients;
	}
}
