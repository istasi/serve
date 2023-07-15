<?php

declare(strict_types=1);

namespace serve\connections\tcp;

use InvalidArgumentException;
use serve\connections;
use serve\interfaces;
use serve\engine;

class listener extends connections\listener implements interfaces\setup
{
	public function __construct(readonly public string|null $address = null, readonly public int|null $port = 8080, readonly public string|null $file = null, engine\pool $pool = null)
	{
		if (empty($address) === false) {
			$streamAddress = 'tcp://'. $address .':'. $port;
		} elseif (empty($file) === false) {

			// In case we were kill -9'd, the socket file may still be around, in which case delete it mnually
			if (file_exists($file) === true) {
				unlink($file);
			}

			$streamAddress = 'unix://'. $file;
		} else {
			throw new InvalidArgumentException('Listener needs either address or a file to listen on');
		}

		$this->setup([
			'address' => $streamAddress
		]);

		if ($pool !== null) {
			$this->options ['pool'] = $pool;
		}

		$stream = stream_socket_server(address: $streamAddress);
		stream_set_blocking(stream: $stream, enable: false);

		if ($file !== null && file_exists($this->file) === true) {
			chmod($this->file, 0666);
		}

		parent::__construct($stream);
	}

	public function read(int $read = 4096): string|false
	{
		$stream = stream_socket_accept($this->stream);
		fclose($stream);

		return false;
	}
}
