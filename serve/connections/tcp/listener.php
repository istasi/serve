<?php

declare(strict_types=1);

namespace serve\connections\tcp;

use serve\connections;
use serve\traits\events;
use serve\traits\setup;
use serve\interfaces;
use serve\log;
use serve\engine;

class listener extends connections\listener implements interfaces\setup
{
	use events;
	use setup;


	public function __construct(array $options)
	{
		$this->options = [
			'address' => '127.0.0.1',
			'port' => 8080,
			'pool' => new engine\pool()
		];
		$this->setup($options);

		$address = 'tcp://'.$this->options['address'].':'.$this->options['port'];
		log::entry('Listener: '. $address);

		$stream = stream_socket_server(address: $address);
		stream_set_blocking(stream: $stream, enable: false);

		parent::__construct($stream);
	}

	public function read(int $read = 4096): string|false
	{
		$stream = stream_socket_accept($this->stream);
		fclose($stream);

		return false;
	}
}
