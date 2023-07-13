<?php

declare(strict_types=1);

namespace serve\connections;

use serve\traits;
use serve\log;

abstract class base
{
	use traits\events;

	protected bool $write = false;
	protected bool $connected = true;
	protected string $message = '';

	public function __construct(readonly public mixed $stream)
	{

	}

	public function __destruct()
	{
		$this->close();
	}

	public function __get(string $key): mixed
	{
		switch ($key) {
			case 'connected':
			case 'write':
				return $this->{$key};
		}
	}

	public function read(int $length = 4096): string|false
	{
		$message = fread($this->stream, $length);

		if ('' === $message) {
			$this->connected = false;

			return false;
		}

		return $message;
	}

	public function write(string $message = ''): void
	{
		$this->message .= $message;

		$length = strlen($this->message);
		$wrote = @fwrite($this->stream, $this->message);
		if ($wrote === false) {
			$this->close();
			return;
		}

		if ($wrote < $length) {
			$this->message = substr($this->message, $wrote, $length);
		} else {
			$this->message = '';
		}

		$this->write = !empty($this->message);
	}

	public function close(): void
	{
		if ($this->connected === false) {
			return;
		}

		$this->connected = false;
		fclose($this->stream);
	}
}
