<?php

declare(strict_types=1);

namespace serve\connections\http;

use serve\connections\http;
use serve\connections\tcp;

class listener extends tcp\listener
{
	public function read(int $read = 4096): string|false
	{
		$stream = @stream_socket_accept(socket: $this->stream, peer_name: $address);
		if ($stream === false) {
			return false;
		}
		stream_set_blocking($stream, false);

		/**
		 * Probably not the cleanest solution, but im not 100% sure how an IPV6 is shown with peer_name
		 * im guessing its ::1:8080
		 */
		$address = strrev($address);
		$bits = explode(':', $address);
		array_shift($bits);
		$address = join(separator: ':', array:$bits);
		$address = strrev($address);

		$connection = new http\client(stream: $stream, address: $address);
		$connection->triggers($this->triggers());

		$this->trigger('accept', [$connection]);

		return false;
	}
}
