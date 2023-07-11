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
        $stream = @stream_socket_accept(socket: $this->stream, peer_name: $address);
		if ($stream === false) {
			return false;
		}
        stream_set_blocking($stream, false);

        $client = new http\client(stream: $stream, address: $address);
        $client->triggers($this->triggers());
        $this->engine->add($client);

        return false;
    }
}
