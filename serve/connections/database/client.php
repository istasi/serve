<?php

declare(strict_types=1);

namespace serve\connections\database;

use serve\connections;
use serve\exceptions\InvalidConnection;

class client extends connections\base
{
	public readonly string $address;
	public readonly string $__address;
	public readonly int $__port;

	public function __construct(string $address, int $port)
	{
		$this->__address = $address;
		$this->__port = $port;

		$this->address = 'tcp://' . $address . ':' . $port;

		$stream =  @stream_socket_client(
			address: $this->address,
			error_code: $code,
			error_message: $message,
			timeout: 1
		);

		if ($stream === false) {
			throw new InvalidConnection($message, $code);
		}

		parent::__construct($stream);
	}
}
