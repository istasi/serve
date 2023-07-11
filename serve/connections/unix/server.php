<?php

declare(strict_types=1);

namespace serve\connections\unix;

use serve\connections\base;
use serve\http\request;
use serve\http\response;
use serve\exceptions\kill;

/**
 * Messages from the server
 *
 * @package serve\connections\unix
 */
class server extends base
{
	private string $lastFiles = '';
	public function checkFiles(response $response, request $request)
	{
		$files = get_included_files();
		$files = json_encode($files);
		if (is_string($files) !== true || $files === $this->lastFiles) {
			return;
		}

		$this->write($files);
		$this->lastFiles = $files;
	}

	public function read(int $length = 4096): string|false
	{
		parent::read($length);

		throw new kill ();

		return false;
	}
}
