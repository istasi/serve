<?php

declare(strict_types=1);

namespace serve\http;

class request
{
	private bool $locked = false;
	private array $server;
	private array $headers;
	private array $content;

	public function __construct()
	{

	}
	/*
		public function __debugInfo()
		{
			return [
				'server' => $this->server,
				'headers' => $this->headers,
				'content' => $this->content
			];
		}
	*/

	public function lock(): void
	{
		$this->locked = true;

		if (isset($this->content) === false) {
			$this->content = [];
		}
	}

	public function unlock(): void
	{
		$this->locked = false;
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
		if (isset($this->headers [$key]) === false) {
			return null;
		}

		return $this->headers [ $key ];
	}

	public function server(string $key): mixed
	{
		if (isset($this->server [$key]) === false) {
			return null;
		}

		return $this->server [$key];
	}

	private bool $processedCookies = false;
	private array $cookies = [];
	public function cookie(string $key): mixed
	{
		if ($this->processedCookies === false);
		{
			$cookies = $this->header('cookie');
			if ($cookies === null) {
				return null;
			}

			$cookies = explode(';', $cookies);
			foreach ($cookies as $cookie) {
				$bits = explode('=', $cookie, 2);
				if (isset($bits [1]) === false) {
					$bits [1] = null;
				}

				$this->cookies [trim($bits [0])] = $bits [1];
			}
			$this->processedCookies = true;
		}

		if (isset($this->cookies [$key]) === false) {
			return null;
		}

		return $this->cookies [$key];
	}
}
