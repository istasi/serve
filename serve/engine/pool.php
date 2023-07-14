<?php

declare(strict_types=1);

namespace serve\engine;

use IteratorAggregate;
use serve\connections;
use serve\traits;

class pool implements IteratorAggregate
{
	use traits\events;
	use traits\setup;
	use traits\streams;

	protected array $pool = [];

	public function __construct(array $options = [])
	{
		// Default options
		$this->options = [
			'workers' => 4
		];

		$this->setup($options);
	}

	public function __debugInfo()
	{
		return [
			'pool' => array_keys($this->pool),
			'options' => $this->options
		];
	}

	public function add(connections\base $connection): void
	{
		if (in_array(haystack: $this->pool, needle: $connection, strict: true) === true) {
			return;
		}

		$connection->trigger('pool_add', ['pool' => $this]);
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

	public function id(): int
	{
		return spl_object_id($this);
	}

	public function get(string $key): mixed
	{
		return $this->options [ $key ] ?? null;
	}

	public function set(string $key, mixed $value): void
	{
		$this->options [ $key ] = $value;
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
