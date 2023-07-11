<?php

declare(strict_types=1);

namespace serve\http;

define ('SERVE_HTTP_REQUEST_WAITING', 1);
define ('SERVE_HTTP_REQUEST_HEADERS', 2);
define ('SERVE_HTTP_REQUEST_BODY', 3);
define ('SERVE_HTTP_REQUEST_COMPLETE', 4);

class request
{
	private bool $locked = false;
	private array $server;
	private array $headers;
	private array $content;

	public function __construct()
	{

	}

	public function __debugInfo()
	{
		return [
			'server' => $this->server,
			'headers' => $this->headers,
			'content' => $this->content
		];
	}

	public function lock(): void
	{
		$this->locked = true;

		if (isset($this->content) === false) {
			$this->content = [];
		}
	}

	public function __server(array $content): void
	{
		$this->server = $content;
	}

	public function __headers(array $content): void
	{
		if ($this->locked) {
			return;
		}

		$this->headers = $content;
	}

	public function __content(array $content): void
	{
		if ($this->locked) {
			return;
		}

		$this->content = $content;
	}

	public function __get($key): mixed
	{
		switch ($key) {
			case 'server':
			case 'headers':
			case 'content':
				return $this->{$key};
		}
	}

	public function header(string $key): mixed
	{
		if (isset($this->headers [ $key]) === false) {
			return null;
		}

		return $this->headers [ $key ];
	}
}
