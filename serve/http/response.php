<?php

declare(strict_types=1);

namespace serve\http;

define ('SERVE_HTTP_RESPONSE_WAITING', 1);
define ('SERVE_HTTP_RESPONSE_HEADERS', 2);
define ('SERVE_HTTP_RESPONSE_BODY', 3);
define ('SERVE_HTTP_RESPONSE_COMPLETE', 4);

class response
{
	private int $state;

	private int $code;
	private array $codeMap = [];

	private array $headers = [];
	private string $response;

	private bool $sent;

	public function __construct ( readonly public request $request, readonly private \serve\connections\connection $connection )
	{
		$this->reset ();

		$this->codeMap = [
			200 => 'OK',
			301 => 'Moved Permanently',
			302 => 'Found',
			400 => 'Bad Request',
			404 => 'Not Found'
		];

		$this->code = 200;
	}

	public function reset (): void
	{
		$this->state = SERVE_HTTP_RESPONSE_WAITING;
		$this->sent = false;

		$this->headers = [];
		$this->response = ''; // HTTP/1.1 200 OK'. "\r\n";

		$this->header ('content-encoding', 'gzip');
		$this->header ('content-type', 'text/html');
		$this->header ('connection', 'keep-alive');
		$this->header ('date', date ('c'));
	}

	public function sent (): bool
	{
		return $this->sent;
	}

	public function header ( string $key, string|null $value ): bool
	{
		if ( $this->sent )
			return false;

		$this->headers [ strtolower ( $key ) ] = $value;

		return true;
	}

	public function redirect ( string $url, int $code = 302 ): void
	{
		$this->code = $code;
		$this->header ('location', $url);
	}

	public function send ( string $content = null, int $code = null ): bool
	{
		$raw = '';
		if ( $code )
			$this->code = $code;

		switch ( $this->state )
		{
			case SERVE_HTTP_RESPONSE_WAITING:
				$raw .= 'HTTP/1.1 '. $this->code .' '. $this->codeMap [ $this->code ] . "\r\n";
				$this->state = SERVE_HTTP_RESPONSE_HEADERS;
			case SERVE_HTTP_RESPONSE_HEADERS:
				$length = strlen ( $content );
				if ( $length > 1024 * 10 )
					$this->headers ['content-encoding'] = 'gzip';
				else if ( $length > 1024 )
					$this->headers ['content-encoding'] = 'deflate';
				else
					unset ( $this->headers ['content-encoding'] );

				if ( empty ( $this->headers ['content-encoding'] ) === false )
				{
					$accept = $this->request->header ('accept-encoding');
					if ( $accept )
					{
						$accept = str_replace (' ', '', $accept);
						$bits = explode (',', $accept );

						if ( in_array ( haystack: $bits, needle: $this->headers ['content-encoding'] ) === false )
							$this->header ('content-encoding', null);
					}

					switch ( $this->headers ['content-encoding'] )
					{
						case 'gzip':
							$content = gzencode($content);
							break;
						case 'deflate':
							$content = gzdeflate($content);
							break;
					}
				}

				$this->header ('content-length', (string) strlen ($content));

				foreach ( $this->headers as $key => $value )
					if ( $value )
						$raw .= $key .': '. $value ."\r\n";

				$this->state = SERVE_HTTP_RESPONSE_BODY;
			case SERVE_HTTP_RESPONSE_BODY:
				$raw .= "\r\n". $content;
				$this->state = SERVE_HTTP_RESPONSE_COMPLETE;
		}

		$this->sent = true;
		$this->connection->write ( $raw );

		$this->reset ();
		$this->request->reset ();

		return true;
	}
}