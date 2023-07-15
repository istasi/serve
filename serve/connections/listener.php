<?php

declare(strict_types=1);

namespace serve\connections;

use serve\traits;

abstract class listener extends base
{
	use traits\events;
	use traits\setup;
}
