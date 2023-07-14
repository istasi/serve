<?php

declare(strict_types=1);

namespace serve\engine;

use IteratorAggregate;
use serve\connections;
use serve\log;
use serve\traits;
use serve\exceptions\kill;
use serve\interfaces;

class client implements interfaces\setup, IteratorAggregate
{
	use traits\events;
	use traits\setup;
	use traits\streams;

	protected array $pool = [];

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

	public function add(connections\base $connection): void
	{
		$this->trigger('add', [$connection]);

		if (in_array(haystack: $this->pool, needle: $connection, strict: true) === true) {
			return;
		}

		$this->pool [] = $connection;
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

	public function run()
	{
		pcntl_async_signals(true);

		pcntl_signal(SIGTERM, function () {
			exit(0);
		});
		pcntl_signal(SIGINT, function () {});

		$address = '';
		$that = $this;
		foreach ($this->getIterator() as $connection) {
			if (($connection instanceof connections\listener) === false) {
				continue;
			} else {
				/** @var connnections\listener $connection */
				$setup = $connection->setup();
				$address = $setup ['address'] .':'. $setup ['port'];
			}

			$connection->on('accept', function ($connection) use ($that) {
				$that->add($connection);
			});
		}
		cli_set_process_title(get_class($this) .' '. $address);

		do {
			foreach ($this->getIterator() as $connection) {
				if ($connection->connected === false) {
					$this->remove($connection);
				}
			}
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

	/**
	 *
	 * @return Traversable<int, connections\base>|connections\base[]
	 */
	public function getIterator(): \Traversable
	{
		foreach ($this->pool as $key => $value) {
			yield $key => $value;
		}
	}
}
