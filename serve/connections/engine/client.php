<?php

declare(strict_types=1);

namespace serve\connections\engine;

use serve\connections\base;
use serve\engine;
use serve\traits;

/**
 * Messages from/to the client
 *
 * @package serve\connections\engine
 */
class client extends base
{
	use traits\engine\getTime;

	private engine\server $server;

	public function __construct(mixed $stream, readonly public int $pid)
	{
		$this->on('pool_added', function ($server) {
			if ($server instanceof engine\server) {
				$this->server($server);
			}
		});

		parent::__construct($stream);
	}

	public function __destruct()
	{
		$this->close();
	}

	private function server(engine\server $server)
	{
		$this->server = $server;
	}

	public function read(int $length = 4096): string|false
	{
		$message = parent::read(4096);
		if (empty($message) === true) {
			$this->close();

			return false;
		}

		switch (substr($message, 0, 1)) {
			case 'p':
				$bits = explode(separator: ':', string: $message, limit: 3);
				$id = $bits [1];
				$options = unserialize($bits[2]);

				$this->server->trigger('pool_change', [ 'id' => $id, 'options' => $options]);
		}

		return false;
	}

	public function write ( string $message = '' ): void
	{
		parent::write ( $message );
	}
}
