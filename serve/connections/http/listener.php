<?php

declare(strict_types=1);

namespace serve\connections\http;

use serve\connections\http;
use serve\connections\tcp;
use serve\log;

class listener extends tcp\listener
{
	public function read(int $read = 4096): string|false
	{
		$stream = stream_socket_accept($this->stream);
		stream_set_blocking($stream, false);

		$client = new http\client($stream);
		$client->triggers($this->triggers());
		$this->engine->add($client);

		return false;
	}
}
