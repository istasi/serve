<?php

declare(strict_types=1);

namespace serve\connections;

use serve\engine;

class listener extends base
{
	protected engine\base $engine;

	public function __construct ( mixed $stream )
	{
		$that = $this;
		$this->on('added', function (engine\base $engine) use ($that) {
			$that->engine($engine);
		});

		parent::__construct ( $stream );
	}

	public function engine(engine\base $engine): void
	{
		$this->engine = $engine;
	}
}
