<?php

declare(strict_types=1);

namespace serve\pools;

use IteratorAggregate;
use Countable;
use Traversable;

class pool implements Countable, IteratorAggregate
{
	protected array $pool;
	public function __construct ( protected int $size = 128 )
	{
		$this->pool = [];
		$this->events = [];
	}

	public function size ( int $size = null ): int
	{
		if ( $size )
			$this->size = $size;

		return $this->size;
	}

	public function add ( $item ): void
	{
		if ( $this->size > -1 && count ( $this->pool ) > $this->size )
		{
			$removedKey = array_key_First ( $this->pool );
			$removedItem = array_shift ( $this->pool );

			$this->trigger ('remove', [ $removedKey, $removedItem ] );
		}

		$this->pool [] = $item;
		$addedKey = array_key_last ( $this->pool );

		$this->trigger ('add', [ $addedKey, $item ] );
	}

	public function remove ( $item ): void
	{
		foreach ( $this->pool as $key => $value )
		{
			if ( $value === $item )
			{
				unset ( $this->pool [$key] );

				$this->trigger ('remove', [ $key, $item ]);
			}
		}

		return;
	}

	private array $events;
	public function on ( string $event, callable $function ): void
	{
		if ( isset ( $this->events [ $event ] ) === false )
			$this->events [ $event ] = [];


		$this->events [ $event ][] = $function;

		return;
	}

	private function trigger ( string $event, array $arguments = [] ): void
	{
		if ( isset ( $this->events [ $event ] ) === false )
			return;

		if ( empty ( $this->events [$event] ) === false )
			foreach ( $this->events [$event] as $function )
				call_user_func_array($function, $arguments);

		return;
	}

	public function count(): int
	{
		return count ( $this->pool );
	}

	public function getIterator(): Traversable
	{
		foreach ( $this->pool as $key => $value )
			yield $key => $value;
	}
}