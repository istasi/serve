<?php

declare(strict_types=1);

namespace serve\connections\unix;

use serve\connections\base;

class server extends base
{
	public function read(int $length = 4096): string|false
	{
		$message = parent::read($length);

		if (false === $this->connected) {
			exit;
		}

		// Handle message
		return '';
	}
}
