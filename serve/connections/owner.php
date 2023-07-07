<?php

declare(strict_types=1);

namespace serve\connections;

use serve\exceptions\kill;

use socket;

/**
 * This is how we interact with owner,
 * read () receives data from the owner
 * write () sends data to the owner
 *
 * @package serve\threads
 */
class owner extends connection
{
	public function __construct ( readonly protected socket $socket )
	{
		$this->opened = true;
	}

	private string $lastFiles = '';
	public function send (): void
	{
		$currentFiles = json_encode ( get_included_files() );

		if ( $this->lastFiles === $currentFiles )
			return;

		$this->lastFiles = $currentFiles;
		$this->write ( $currentFiles );
	}

	private string $read = '';
	public function read(int $length = 4096): string|false
	{
		$this->read .= parent::read ( $length );

		if ( !str_ends_with ( haystack: $this->read, needle: PHP_EOL ) )
			return false;

		$lines = explode ( PHP_EOL, $this->read );

		foreach ( $lines as $line )
			if ( $line && is_numeric ( $line ) )
				switch ( $line )
				{
					case ENGINE_WORKER_DIE:
						$this->close ();
						throw new kill ();
						break;
				}

		return false;
	}

	public function write(string $message = null): void
	{
		parent::write ( $message . PHP_EOL );
	}

	public function ready (): void
	{
		$this->write ( ENGINE_WORKER_READY );
	}
}