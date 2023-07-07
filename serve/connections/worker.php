<?php

declare(strict_types=1);

namespace serve\connections;

use socket;

/**
 * This is how we interact with workers,
 * read () receives data from the workers
 * write () sends data to the worker
 *
 * @package serve\threads
 */
class worker extends \serve\connections\connection
{
	private array $files = [];

	public function __construct ( readonly protected socket $socket )
	{
		$this->opened = true;
	}

	private string $read = '';

	public function read(int $length = 4096): string|false
	{
		$this->read .= parent::read ( $length );

		if ( !str_ends_with ( haystack: $this->read, needle: PHP_EOL ) )
			return false;

		$lines = explode ( PHP_EOL, $this->read );

		foreach ( $lines as $line )
			if ( !is_numeric ( $line ) && $line )
			{
				$files = json_decode ( $line );

				if ( $files )
					$this->files = array_merge ( [], $files );
			}

		return false;
	}

	public function die (): void
	{
		$this->write ( ENGINE_WORKER_DIE );

		$this->close ();
	}

	public function write(string $message = null): void
	{
		parent::write ( $message . PHP_EOL );
	}

	public function files (): array
	{
		return $this->files;
	}
}