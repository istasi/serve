<?php

declare(strict_types=1);

namespace serve\connections\http\ssl;

use InvalidArgumentException;
use Exception;
use serve\connections\http;
use serve\connections\tcp;
use serve\log;
use serve\engine;

class listener extends http\listener
{
	public function read(int $read = 4096): string|false
	{
		$stream = @stream_socket_accept(socket: $this->stream, peer_name: $address);
		if ($stream === false) {
			return false;
		}
		stream_set_blocking($stream, true);

		$crypto_method = STREAM_CRYPTO_METHOD_TLS_SERVER;
		if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_SERVER')) {
			$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_SERVER;
			$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;
		}

		foreach ($this->options ['ssl'] as $key => $value) {
			stream_context_set_option($this->stream, 'ssl', $key, $value);
		}

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			if ($errno === E_WARNING) {
				$bits = explode(':', $errstr);
				$useful = array_pop($bits);

				log::entry('Warning: '. $useful);
			} else {
				throw new Exception('Debug Fun: '. $errstr .': '. $errfile .': '. $errline);
			}
		});

		// Getting wierd errors here
		$result = stream_socket_enable_crypto($stream, true, $crypto_method);
		restore_error_handler();

		if ($result === false) {
			fclose($stream);

			return false;
		}

		stream_set_blocking($stream, false);

		$connection = new http\client(stream: $stream, address: $address);
		$connection->triggers($this->triggers());

		$this->trigger ('accept', [$connection]);

		return false;
	}
}
