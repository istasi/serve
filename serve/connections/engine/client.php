<?php

declare(strict_types=1);

namespace serve\connections\engine;

use serve\connections\base;
use serve\threads\thread;

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

	public function __construct(mixed $stream, readonly public int $pid)
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
		static $localFiles = null;

		$message = parent::read(4096);
		if (empty($message) === true) {
			$this->close();
			
			/**
			 * A client process is dying
			 */
			thread::wait(0);
		}

		$this->buffer .= $message;

		$result = json_decode($this->buffer, true);
		if ($result !== null) {
			if ($localFiles === null) {
				$localFiles = get_included_files();
			}

			$this->files = array_flip($result);
			foreach ($this->files as $file => $time) {
				if (in_array(haystack: $localFiles, needle: $file, strict: true) === true) {
					unset($this->files [$file]);
				} else {
					$this->files [ $file ] = $this->getTime($file);
				}
			}

			$this->buffer = '';
		}

		return false;
	}
}
