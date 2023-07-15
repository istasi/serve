<?php

declare(strict_types=1);

namespace serve\engine;

use Closure;
use Exception;
use InvalidArgumentException;
use IteratorAggregate;
use serve;
use serve\connections;
use serve\threads\thread;
use serve\traits;
use serve\interfaces;

class server implements interfaces\setup, IteratorAggregate
{
	use traits\events;
	use traits\setup;
	use traits\streams;

	protected array $pool = [];

	public function __construct(array $options = [])
	{
		$this->options = [
			'internal_delay' => 1,
		];

		$this->setup($options);
	}

	public function __destruct()
	{
		foreach ($this->getIterator() as $connection) {
			if ($connection instanceof connections\listener) {
				if (empty($connection->file) === false) {
					unlink($connection->file);
				}
			}

			$connection->close();
		}
	}

	/**
	 * Adds a listener to the engine to monitor
	 *
	 * @param listener $listener
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function add(connections\listener $listener): void
	{
		$this->trigger('add', [$listener]);

		if (in_array(haystack: $this->pool, needle: $listener, strict: true) === true) {
			return;
		}

		$listener->trigger('pool_added', [$this]);

		$this->pool [] = $listener;
	}

	private function addClient(connections\engine\client $client): void
	{
		if (in_array(haystack: $this->pool, needle: $client, strict: true) === true) {
			return;
		}

		$client->trigger('pool_added', [$this]);

		$this->pool [] = $client;
	}

	public function remove(connections\base $connection): void
	{
		foreach ($this->pool as $i => $conn) {
			if ($conn === $connection) {
				unset($this->pool [$i]);
				break;
			}
		}
	}

	public function run(): void
	{
		cli_set_process_title(__CLASS__);

		/** @var engine\pool[] $pools */
		$pools = [];

		foreach ($this->getIterator() as $connection) {
			if ($connection instanceof interfaces\setup) {
				$options = $connection->setup();

				if (isset($options ['pool']) === false || ($options ['pool'] instanceof serve\engine\pool) === false) {
					if (isset($defaultPool) === false) {
						$defaultPool = new serve\engine\pool();
					}

					$options ['pool'] = $defaultPool;
				}

				/** @var engine\pool $pool */
				$pool = $options ['pool'];
				if (isset($pools [ $pool->id() ]) === false) {
					$pool->on('add', function ($connection) {
						if ($connection instanceof connections\engine\client) {
							$this->addClient($connection);
						}
					});
					$pools [ $pool->id() ] = $pool;
				}

				$pool->add($connection);
			}


			$this->add($connection);
		}
		unset($connection, $options, $pool, $defaultPool);

		/**
		 * We still want __destruct to run if we get killed, atleast to clean up 
		 */
		pcntl_async_signals(true);
		$fn = function () {
			$this->__destruct();
			
			exit(0);
		};
		pcntl_signal(SIGTERM, $fn);
		pcntl_signal(SIGINT, $fn);

		do {
			$read = [];
			foreach ($pools as $pool) {
				$workers = $pool->get('workers');

				$pool->streams(function ($connection) use (&$workers, &$read) {
					if ($connection instanceof connections\engine\client) {
						$workers--;

						$read [] = $connection->stream;
					}
				});

				if ($workers > 0) {
					$connections = [];
					foreach ($pool->getIterator() as $connection) {
						if (($connection instanceof serve\engine\client) === false) {
							$connections [] = $connection;
						}
					}

					$workers = $this->spawn($workers, $connections);
					foreach ($workers as $connection) {
						$pool->add($connection);
						$read [] = $connection->stream;
					}
				}
			}
			unset($workers, $connections, $connection, $pool);
			//$read = array_unique($read);

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
				if (null === $connection) {
					continue;
				}

				$message = $connection->read();
				if (empty($message) === false) {
					serve\log::entry($message);
				}
			}

			foreach ($write as $connection) {
				$connection = $this->fromStream($connection);
				if (null === $connection) {
					continue;
				}

				$connection->write();
			}
			unset($connection);

			//break;
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
				$engine = new serve\engine\client();
				$engine->add($server);

				/** @var connections\base[] $connections */
				foreach ($connections as $connection) {
					$connection->trigger('worker_start');
					$connection->trigger('setup');

					$engine->add($connection);
				}

				$engine->run();

				exit(0);
			});
		}

		return $clients;
	}

	/**
	 *
	 * @return Traversable<int, connections\base>|connections\base[]
	 */
	public function getIterator(): \Traversable
	{
		foreach ($this->pool as $key => $value) {
			if ($value->connected === false) {
				unset($this->pool [ $key ]);
				continue;
			}

			yield $key => $value;
		}
	}

	private function halt()
	{
		foreach ($this->getIterator() as $connection) {
			$connection->close();
		}
	}
}
