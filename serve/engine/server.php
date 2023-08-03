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

class server implements IteratorAggregate
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
				if (empty($connection->file) === false && file_exists($connection->file) === true) {
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

		$this->pool[] = $listener;
	}

	private function addClient(connections\engine\client $client): void
	{
		if (in_array(haystack: $this->pool, needle: $client, strict: true) === true) {
			return;
		}

		$client->trigger('pool_added', [$this]);

		$this->pool[] = $client;
	}

	public function remove(connections\base $connection): void
	{
		foreach ($this->pool as $i => $conn) {
			if ($conn === $connection) {
				unset($this->pool[$i]);
				break;
			}
		}
	}

	public function run(): void
	{
		cli_set_process_title(__CLASS__);

		/** @var engine\pool[] $pools */
		$pools = [];

		$this->on('pool_change', function (int $id, array $options) use (&$pools) {
			if (isset($pools[$id]) === true) {
				$pools[$id]->setup($options);
			}
		});

		foreach ($this->getIterator() as $connection) {
			if ($connection instanceof connections\listener) {
				if ($connection->pool === null) {
					if (isset($defaultPool) === false) {
						$defaultPool = new serve\engine\pool();
					}

					$pool = $defaultPool;
				} else {
					$pool = $connection->pool;
				}

				/** @var engine\pool $pool */
				if (isset($pools[$pool->id()]) === false) {
					$pool->on('add', function ($connection) {
						if ($connection instanceof connections\engine\client) {
							$this->addClient($connection);
						}
					});
					$pools[$pool->id()] = $pool;
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
			while (thread::wait() > 0);

			$read = [];
			foreach ($pools as $pool) {
				$workers = $pool->get('workers');

				foreach ($pool->getIterator() as $connection) {
					if ($connection->connected === false) {
						$pool->remove($connection);
						$this->remove($connection);

						continue;
					}

					if ($connection instanceof connections\engine\client) {
						$workers--;

						if ($workers < 0) {
							$connection->write('die');

							continue;
						}

						$read[] = $connection->stream;
					}
				}


				if ($workers > 0) {
					$connections = [];
					foreach ($pool->getIterator() as $connection) {
						if (($connection instanceof connections\engine\client) === false) {
							$connections[] = $connection;
						}
					}

					$clients = $this->spawn($workers, $connections);
					foreach ($clients as $connection) {
						$pool->add($connection);
						$read[] = $connection->stream;
					}
				}
			}
			unset($workers, $connections, $connection, $pool, $clients);
			//$read = array_unique($read);

			$write = $this->streams(function ($connection) {
				return true === $connection->writing;
			});
			$except = [];

			$changes = @stream_select(
				read: $read,
				write: $write,
				except: $except,
				seconds: $this->options['internal_delay'],
				microseconds: 0
			);
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

				if ($connection->connected === false) {
					$this->remove($connection);

					if (isset($connection->pool) === true) {
						$connection->pool->remove($connection);
					}
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

		exit(0);
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
			$clients[] = thread::spawn(function ($server) use ($connections) {
				$this->halt();

				$pools = [];
				foreach ($connections as $connection) {
					if (in_array(haystack: $pools, needle: $connection->pool, strict: true) === false) {
						$pools[] = $connection->pool;
						$connection->pool->trigger('start');
					}
				}
				unset($polls, $connection);

				$engine = new serve\engine\client(server: $server);
				$engine->add(connection: $server);

				/** @var connections\base[] $connections */
				foreach ($connections as $connection) {
					$engine->add(connection: $connection);
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
				unset($this->pool[$key]);
				continue;
			}

			yield $key => $value;
		}
	}

	private function halt()
	{
		$this->triggers([]);
		/*
		foreach ($this->getIterator() as $connection) {
			$connection->close();
		}
		*/
	}
}
