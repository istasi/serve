<?php

declare(strict_types=1);

namespace serve\pools;

use serve\traits;

class pool implements \Countable, \IteratorAggregate
{
	use traits\events;

	protected array $pool;

	public function __construct(protected int $size = 128)
	{
		$this->pool = [];
	}

	public function size(int $size = null): int
	{
		if ($size) {
			$this->size = $size;
		}

		return $this->size;
	}

	public function add(mixed $item): void
	{
		if ($this->size > -1 && count($this->pool) > $this->size) {
			$removedKey = array_key_first($this->pool);
			$removedItem = array_shift($this->pool);

			$this->trigger('remove', [$removedKey, $removedItem]);
		}

		$this->pool[] = $item;
		$addedKey = array_key_last($this->pool);

		$this->trigger('add', [$addedKey, $item]);
	}

	public function remove($item): void
	{
		foreach ($this->pool as $key => $value) {
			if ($value === $item) {
				unset($this->pool[$key]);

				$this->trigger('remove', [$key, $item]);
			}
		}
	}

	public function count(): int
	{
		return count($this->pool);
	}

	public function getIterator(): \Traversable
	{
		foreach ($this->pool as $key => $value) {
			yield $key => $value;
		}
	}
}
