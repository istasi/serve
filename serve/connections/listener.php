<?php

declare(strict_types=1);

namespace serve\connections;

use serve\traits;
use serve\engine;

abstract class listener extends base
{
	use traits\events;
	use traits\setup;

	protected engine\pool $pool;

	public function __get($key): mixed
	{
		switch ($key) {
			case 'pool':
				return $this->{$key};
		}

		return parent::__get($key);
	}
}
