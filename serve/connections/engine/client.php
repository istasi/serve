<?php

declare(strict_types=1);

namespace serve\connections\engine;

use serve\connections\base;

if (function_exists('inotify_init') === true) {
	require_once(__DIR__ .'/trait/usingInotify.php');
} else {
	require_once(__DIR__ .'/trait/usingFilemtime.php');
}


/**
 * Messages from the client
 *
 * @package serve\connections\engine
 */
class client extends base
{
	use trait\getTime;

	public function __construct(mixed $stream)
	{
		parent::__construct($stream);
	}

	public function __destruct()
	{
		$this->close();
	}

	private array $files = [];

	protected string $buffer = '';
	public function read(int $length = 4096): string|false
	{
		$this->buffer .= parent::read(4096);

		$result = json_decode($this->buffer, true);
		if ($result !== null) {
			$this->files = array_flip($result);
			foreach ($this->files as $file => $time) {
				$this->files [ $file ] = $this->getTime($file);
			}

			$this->buffer = '';
		}

		return false;
	}

	public function tick(): void
	{
		foreach ($this->files as $file => $time) {
			if ($time < $this->getTime($file)) {
				$this->write('die');
			}
		}
	}
}
