<?php

declare(strict_types=1);

namespace serve\engine;

use serve\connections;
use serve\connections\engine\client;
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

	public function __destruct()
	{
		foreach ($this as $connection) {
			$connection->close();
		}
	}

	public function run(): void
	{
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
		pcntl_signal(SIGTERM, function () { exit(0); });
		pcntl_signal(SIGINT, function () {});

		$workers = $this->options['workers'];

		do {
			/**
			 * No idea why this matters, but having this here, seem to make sure that the engine\client are consistently killed off when engine\server is killed (SIGTERM/SIGINT)
			 */
			pcntl_signal_dispatch();

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

			$changes = @stream_select(read: $read, write: $write, except: $except, seconds: $this->options['internal_delay'], microseconds: 0);
			if ($changes === false) {
				break;
			}

			if ($changes < 1) {
				foreach ($this as $connection) {
					if ($connection instanceof client) {
						$connection->tick();
					}
				}

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

			if (thread::wait()) {
				var_dump(thread::lastExit());
			}
		} while (1);

		thread::killall();
	}

	public function spawn(int $amount): void
	{
		$original = $this;

		for ($i = 0; $i < $amount; ++$i) {
			$client = thread::spawn(function ($server) use ($original) {
				$engine = new engine\client();
				$engine->setup($original->setup());
				$engine->add($server);

				$original->trigger('worker_start', []);

				foreach ($original as $connection) {
					if ($connection instanceof connections\listener) {
						$connection->on('request', [$server, 'checkFiles']);

						$engine->add($connection);
					}
				}

				$engine->run();

				$original->trigger('worker_end', []);
			});

			$this->add($client);
		}
	}
}
