<?php

declare(strict_types=1);

namespace serve\http;

use serve\http\one\writer;

class response
{
	private writer $writer;
	private int $state = 0;

	private array $headers = [
		'connection' => ['keep-alive'],
		'content-type' => ['text/html; charset=utf8']
	];


	public int $code = 200;


	public function setWriter(writer $writer)
	{
		$this->writer = $writer;
	}

	public function header(string $key, mixed $value): bool
	{
		if ($this->state > 1) {
			return false;
		}

		$key = strtolower($key);
		if (isset($this->headers [ $key ]) === false) {
			$this->headers [ $key ] = [];
		}

		$this->headers [ $key ][] = $value;
		return true;
	}

	public function send(string $body): void
	{
		switch ($this->state) {
			case 0:
				$this->writer->response($this->code);
				$this->state = 1;
				// no break
			case 1:
				$this->writer->headers($this->headers);
				$this->state = 2;
				// no break
			case 2:
				$this->writer->content($body);

				$this->state = 0;
				$this->headers = [];
				$this->code = 200;
		}
	}
}
