<?php

declare(strict_types=1);

namespace serve\connections\database\mysql;

use serve\connections\database;
use serve\exceptions\InvalidConnection;
use PDO;
use PDOException;

class listener extends database\listener
{
	public function __construct(string|null $address = null, int|null $port = 8080, string|null $file = null, array $options = [])
	{
		parent::__construct($address, $port, $file, $options);

		$this->on('pool', function ($pool) {
			$pool->on('start', function () {
				if (isset($this->options['database']) === false) {
					\serve\log::entry(get_class($this) . '::on("start"): Missing database');
					return;
				}

				$this->options['database']->connect();
			});
		});
	}

	public function read(int $length = 4096): string|false
	{
		$stream = @stream_socket_accept(socket: $this->stream);
		if ($stream === false) {
			return false;
		}

		$message = '';
		while (true) {
			$message .= fread($stream, 1024 * 1024);
			if ($message === '') {
				fclose($stream);
				return false;
			}

			$bits = [
				substr(string: $message, offset: 0, length: 4),
				substr(string: $message, offset: 4)
			];
			$length = unpack('i', $bits[0]);
			if (empty($length) === true) {
				fclose($stream);
				return false;
			}
			$length = current($length);

			if ((strlen($message) - 4) < $length) {
				continue;
			}
			$message = $bits[1];

			$json = json_decode($message);
			if ($json !== null) {
				try {
					$result = $this->options['database']->query($json->query, $json->arguments);
					if ($result === false) {
						$result = 'Unable to execute query: '. $json->query .' arguments: '. json_encode ( $json->arguments );
					}
				} catch (PDOException $e) {
					$result = $e->getMessage();
				}

				$read = [$stream];
				if (($changes = stream_select($read, $write, $except, 0, 0)) !== false && $changes > 0) {
					fclose($stream);
					return false;
				}

				$serialized = serialize($result);
				$message = pack('i', strlen($serialized)) . $serialized;

				if (is_resource($stream) === true) {
					@fwrite($stream, $message);
				}
			}

			fclose($stream);
			return false;
		}
	}
}
