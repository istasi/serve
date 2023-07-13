<?php

declare(strict_types=1);

namespace serve\connections;

use serve\engine;

abstract class listener extends base
{
	protected engine\base $engine;

	public function __construct(mixed $stream)
	{
		parent::__construct($stream);
	}
}
