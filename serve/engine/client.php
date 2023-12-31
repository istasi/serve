<?php

declare(strict_types=1);

namespace serve\engine;

use Exception;
use Throwable;
use IteratorAggregate;
use serve\connections;
use serve\log;
use serve\traits;
use serve\exceptions\kill;

class client implements IteratorAggregate
{
	use traits\events;
	use traits\setup;
	use traits\streams;

	protected array $pool = [];

	public function __construct(readonly public connections\engine\server $server)
	{
		$this->options = [
			'internal_delay' => 30
		];
	}

	public function __destruct()
	{
		foreach ($this as $connection) {
			$connection->close();
		}
	}

	public function add(connections\base $connection): void
	{
		$this->trigger('add', [$connection]);

		if (in_array(haystack: $this->pool, needle: $connection, strict: true) === true) {
			return;
		}

		$connection->trigger('pool_added', [$this]);

		$this->pool[] = $connection;
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

	public function run()
	{
		pcntl_async_signals(true);
		$fn = function () {
			$this->__destruct();

			exit(0);
		};
		pcntl_signal(SIGTERM, $fn);
		pcntl_signal(SIGINT, $fn);

		$pools = [];
		$address = [];
		$that = $this;
		foreach ($this->getIterator() as $connection) {
			if ($connection instanceof connections\listener) {
				/** @var connnections\listener $connection */
				$setup = $connection->setup();

				if (isset($address[get_class($connection)]) === false) {
					$address[get_class($connection)] = [];
				}
				$address[get_class($connection)][] = $setup['address'];

				if (in_array(haystack: $pools, needle: $connection->pool, strict: true) === false) {
					$pools[] = $connection->pool;
				}
			} else {
				continue;
			}

			$connection->on('accept', function ($connection) use ($that) {
				$that->add($connection);
			});
		}

		foreach ($pools as $pool) {
			$pool->on('change', function (string $key, mixed $value) use ($pool) {
				$this->server->write('p:' . $pool->id() . ':' . serialize([$key => $value]));
			});
		}

		$title = '';
		foreach ($address as $class => $listens) {
			$title .= $class . ' ' . join(separator: ' ', array: $listens) . ' ';
		}
		cli_set_process_title(title: trim($title));
		unset($address, $title, $connection, $setup, $pools, $pool);

		do {
			$read = $this->streams();
			$write = $this->streams(function ($connection) {
				return true === $connection->writing;
			});
			$except = [];

			$changes = @stream_select(read: $read, write: $write, except: $except, seconds: $this->options['internal_delay'] ?? 30, microseconds: 0);
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
				} catch (Throwable $e) {
					if ($e instanceof kill) {
						break 2;
					}

					log::entry($e->__toString());
					$connection->close ();
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
}
