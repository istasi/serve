<?php

declare(strict_types=1);

namespace serve\engine;

use serve\connections;

class pool extends base
{
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
			'pool' => array_keys ( $this->pool ),
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
}
