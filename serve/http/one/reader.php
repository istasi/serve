<?php

declare(strict_types=1);

namespace serve\http\one;

use Traversable;
use Generator;
use serve\http;

class reader
{
	private int $state = 0;
	private http\request $request;

	private string $address = '';
	private string $current = '';

	private string $remaining = '';

	public function __construct()
	{
		$this->request = new http\request();
	}

	public function address(string $address = null): string
	{
		if ($address !== null) {
			$this->address = $address;
		}

		return $this->address;
	}

	public function text(string $remaining = null): string
	{
		if ($remaining === null) {
			return $this->remaining;
		}

		$this->remaining .= $remaining;
		return '';
	}

	public function clear(): void
	{
		$this->current = '';
		$this->remaining = '';
	}

	public function parse(): Generator
	{
		switch ($this->state) {
			case 0:
				$sections = explode("\r\n\r\n", $this->remaining, 2);
				if (count($sections) !== 2) {
					return null;
				}
				$this->request->unlock();
				$this->current = $sections [0];
				$this->remaining = $sections [1];

				// no break
			case 1:
				// Step 1: GET / HTTP/1.1
				$lines = explode("\r\n", $this->current);

				$line = $lines [0];
				$bits = explode(' ', $line);
				if (count($bits) !== 3) {
					return null;
				}

				$server = [
					'method' => $bits [0],
					'request_uri' => $bits [1],
					'protocol' => $bits [2],
					'remote_addr' => $this->address, // TODO: Figure a way to split this out between IP and PORT, not sure explode on ':' would do well with with IPv6
				];
				$this->request->__server($server);

				$this->state = 1;

				// no break
			case 2:
				// Step 2: Connection: keep-alive
				if (isset($lines) === false) {
					$lines = explode("\r\n", $this->current);
				}
				$headers = [];

				// We do not have to check if we have received all headers or not, as step 0 wouldn't allow us to reach this point if we do not
				$linesCount = count($lines);
				for ($i = 1; $i < $linesCount; $i++) {
					$line = $lines [$i];

					$bits = explode(':', $line, 2);
					if (count($bits) !== 2) {
						$bits [1] = null;
					}

					$headers [ rtrim(strtolower($bits [0])) ] = ltrim($bits [1]);
				}

				$this->request->__headers($headers);
				$this->state = 2;
				// no break
			case 2:
				// Step 2: body of the request
				$length = (int) $this->request->header('content-length');
				if ($length > 0) {
					$body = substr($this->remaining, 0, $length);

					if (strlen($body) >= $length) {
						$this->remaining = substr($this->remaining, $length);
					} else {
						return null;
					}

					switch ($this->request->header('content-type')) {
						case 'application/json':
							$result = json_decode($body, true);
							break;
						case 'application/x-www-form-urlencode':
						default:
							$result = [];
							parse_str($body, $result);
							break;
					}

					$this->request->__content($result);
				}

				$this->state = 3;
				// no break
			case 3:
				// Step 3: We are done
				$this->request->lock();
				$this->state = 0;

				yield $this->request;
		}
	}

	public function getIterator(): Traversable
	{
		return $this->parse();
	}
}
