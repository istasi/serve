<?php

declare(strict_types=1);

namespace serve\connections\unix;

use serve\connections\base;

/**
 * Messages from the client
 *
 * @package serve\connections\unix
 */
class client extends base
{
	private array $files = [];

	public function files(): array
	{
		return $this->files;
	}

	protected string $buffer = '';
	public function read(int $length = 4096): string|false
	{
		$this->buffer .= parent::read(4096);

		$result = json_decode($this->buffer, true);
		if ($result !== null) {
			$this->files = $result;
			$this->buffer = '';
		}

		return false;
	}
}
