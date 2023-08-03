<?php

declare(strict_types=1);

namespace serve\connections;

use serve\engine;
use serve\traits;
use serve\log;

abstract class base
{
	use traits\events;

	protected bool $writing = false;
	protected bool $connected = true;
	protected string $message = '';

	public function __construct(protected mixed $stream)
	{
	}

	public function __destruct()
	{
		$this->close();
	}

	public function __get(string $key): mixed
	{
		switch ($key) {
			case 'stream':
			case 'connected':
			case 'writing':
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
		if ($this->connected === false) {
			return;
		}
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

		$this->writing = !empty($this->message);
	}

	public function close(): void
	{
		if ($this->connected === false) {
			return;
		}

		$this->connected = false;

		if (isset($this->stream) === true && is_resource($this->stream) === true) {
			fclose($this->stream);
		}
	}
}
