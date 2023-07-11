<?php

declare(strict_types=1);

namespace serve\engine;

use serve\connections;
use serve\pools;
use serve\traits;

class base extends pools\connections
{
	use traits\events;

	public function add(mixed $connection): void
	{
		if (!$connection instanceof connections\base) {
			throw new \InvalidArgumentException('$connection added is not instanceof \\serve\\connections\\base');
		}

		$connection->trigger('added', [$this]);

		parent::add($connection);
	}
}
