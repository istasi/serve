<?php

declare(strict_types=1);

namespace serve\engine;

use Countable;
use IteratorAggregate;
use serve\connections;
use serve\traits;
use Traversable;

abstract class base implements Countable, IteratorAggregate
{
	use traits\events;
	use traits\setup;

	protected array $pool = [];

	public function add(connections\base $connection): void
	{
		$this->trigger ('add', [$connection]);

		if (in_array(haystack: $this->pool, needle: $connection, strict: true) === true) {
			return;
		}

		$this->pool [] = $connection;
	}

	public function remove(connections\base $connection): void
	{
		foreach ($this->pool as $i => $conn) {
			if ($connection === $conn) {
				unset($this->pool [$i]);

				return;
			}
		}

		return;
	}

	public function streams(callable $filter = null): array
	{
		$streams = [];

		foreach ($this as $connection) {
			if (!$connection->connected) {
				$this->remove($connection);

				continue;
			}

			if ($filter) {
				if ($filter($connection)) {
					$streams[] = $connection->stream;
				}
			} else {
				$streams[] = $connection->stream;
			}
		}

		return $streams;
	}

	public function fromStream($stream): \serve\connections\base|null
	{
		foreach ($this as $connection) {
			if ($connection->stream === $stream) {
				return $connection;
			}
		}

		return null;
	}

	public function count(): int
	{
		return count($this->pool);
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
