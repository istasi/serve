<?php

declare(strict_types=1);

namespace serve\pools;

class connections extends pool
{
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
}
