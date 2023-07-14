<?php

declare(strict_types=1);

namespace serve\traits;

trait streams
{
	public function streams(callable $filter = null): array
	{
		$streams = [];

		foreach ($this as $connection) {
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
}
