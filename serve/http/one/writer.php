<?php

declare(strict_types=1);

namespace serve\http\one;

use serve\connections\http\client;

require_once(__DIR__ .'/../constants/response_codes.php');

class writer
{
	private int $state = 0;
	private client $client;
	private string $message = '';

	public function client(client $client)
	{
		$this->client = $client;
	}

	public function response(int $code): bool
	{
		if ($this->state !== 0) {
			return false;
		}

		$this->state = 1;
		//$this->client->write('HTTP/1.1 '. $code .' '. constant('SERVE_HTTP_RESPONSE_CODES_'. $code));
		$this->message = 'HTTP/1.1 '. $code .' '. constant('SERVE_HTTP_RESPONSE_CODES_'. $code) ."\r\n";

		return true;
	}

	public function headers(array $headers): bool
	{
		if ($this->state !== 1) {
			return false;
		}

		$message = '';
		unset($headers ['content-length']);
		foreach ($headers as $key => $values) {
			foreach ($values as $value) {
				$message .= $key .': '. $value ."\r\n";
			}
		}

		$this->state = 2;
		if (empty($message) === false) {
			//$this->client->write($message);
			$this->message .= $message;
		}

		return true;
	}

	public function content(string|false $message): bool
	{
		if ($this->state !== 2) {
			return false;
		}

		$this->state = 0;
		if (empty($message) === true) {
			$this->client->write("\r\n\r\n\r\n");
		} else {
			$message = 'content-length: '. strlen($message) ."\r\n\r\n". $message;
			$this->client->write($this->message . $message);
		}

		return true;
	}
}
