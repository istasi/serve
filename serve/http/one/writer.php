<?php

declare(strict_types=1);

namespace serve\http\one;

use serve\connections\http\client;

require_once(__DIR__ . '/../constants/response_codes.php');

class writer
{
	private int $state = 0;
	private client $client;
	private string $message = '';
	private array $headers = [];

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
		$this->message = 'HTTP/1.1 ' . $code . ' ' . constant('SERVE_HTTP_RESPONSE_CODES_' . $code) . "\r\n";

		return true;
	}

	public function headers(array $headers): bool
	{
		if ($this->state !== 1) {
			return false;
		}

		if (isset($headers['content-type']) === false) {
			$headers['content-type'] = ['gzip'];
		}

		if (isset($headers['content-type']) === true && str_starts_with(haystack: $headers['content-type'][0], needle: 'image') === true && $headers['content-type'][0] !== 'image/svg+xml') {
			unset($headers['content-encoding']);
		}

		if (isset($headers['content-encoding']) === true) {
			$encoding = $headers['content-encoding'][0];
			if (is_string($encoding) === true) {
				$encoding = explode(',', $encoding);
				$encoding = trim($encoding[0]);

				$headers['content-encoding'] = [$encoding];
			} else {
				unset($headers['content-encoding']);
			}
		}

		switch ($headers['content-encoding'][0] ?? '') {
			case 'gzip':
			case 'deflate':
				break;
			default:
				unset($headers['content-encoding']);
		}

		$this->headers = $headers;

		$message = '';
		unset($headers['content-length']);
		foreach ($headers as $key => $values) {
			foreach ($values as $value) {
				$message .= $key . ': ' . $value . "\r\n";
			}
		}

		$this->state = 2;
		if (empty($message) === false) {
			$this->message .= $message;
		}

		return true;
	}

	public function content(string|false $message): bool
	{
		if ($this->state !== 2) {
			return false;
		}

		if (isset($this->headers['content-encoding']) === true) {
			$encoding = $this->headers['content-encoding'][0];
		} else {
			$encoding = '';
		}

		$message = rtrim($message, "\r\n");
		$length = strlen($message);
		if ($length > 1024 * 128) {
			$level = 0;
		} else {
			$level = -1;
		}

		switch ($encoding) {
			case 'gzip':
				$message = gzencode($message, $level);
				break;

			case 'deflate':
				$message = gzdeflate($message, $level);
				break;
		}

		$this->message .= 'content-length: ' . strlen($message) . "\r\n\r\n" . $message;

		$this->client->write($this->message);
		$this->message = '';

		$this->state = 0;
		return true;
	}
}
