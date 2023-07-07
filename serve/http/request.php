<?php

declare(strict_types=1);

namespace serve\http;

define ('SERVE_HTTP_REQUEST_WAITING', 1);
define ('SERVE_HTTP_REQUEST_HEADERS', 2);
define ('SERVE_HTTP_REQUEST_BODY', 3);
define ('SERVE_HTTP_REQUEST_COMPLETE', 4);

class request
{
	private string $raw;
	private ?string $body;
	private int $state;

	public array $headers;
	public array $server;
	public array $cookies;

	public function __construct ( string $text = '' )
	{
		$this->reset ();

		$this->append ( $text );
	}

	/*
	public function __debugInfo ()
	{
		return [
			'headers' => $this->headers,
			'server' => $this->server,
			'cookies' => $this->cookies,
			'body' => $this->body
		];
	}
	*/

	private string $address;
	public function address ( string $address = null ): string
	{
		if ( $address )
			$this->address = $address;

		return $this->address;
	}

	public function append ( string $text ): void
	{
		$this->raw .= $text;
		switch ( $this->state )
		{
			case SERVE_HTTP_REQUEST_WAITING:
				$bits = explode ("\r\n", $this->raw, 2);
				if ( count ( $bits ) < 2 )
					return;

				$this->raw = $bits [1];
				$bits = explode (' ', $bits[0], 3);
				$this->server ['request_method'] = $bits [0];
				$this->server ['request_uri'] = $bits [1];
				$this->server ['server_protocol'] = $bits [2];

				$this->state = SERVE_HTTP_REQUEST_HEADERS;
			case SERVE_HTTP_REQUEST_HEADERS:
				$bits = explode ("\r\n\r\n", $this->raw, 2);
				if ( count ( $bits ) < 2 )
					return;

				$this->raw = $bits [1];
				$this->parse ( $bits [0] );

				if ( !$this->header ('content-length') )
				{
					$this->state = SERVE_HTTP_REQUEST_COMPLETE;
					return;
				}

				$this->state = SERVE_HTTP_REQUEST_BODY;
			case SERVE_HTTP_REQUEST_BODY:
				$length = $this->header('content-length');
				if ( strlen ( $this->raw ) < $length )
					return;

				$this->body = $this->raw;
				$this->state = SERVE_HTTP_REQUEST_COMPLETE;
		}

		return;
	}

	public function complete (): bool
	{
		return $this->state === SERVE_HTTP_REQUEST_COMPLETE;
	}

	public function parse ( string $raw ): void
	{	
		$lines = explode ("\r\n", $raw );

		$headers = [];
		foreach ( $lines as $line )
		{
			$bits = explode (':', $line, 2);

			if ( count ( $bits ) === 2 )
				$headers [ strtolower ( trim ( $bits [0] ) ) ] = trim ( $bits [1] );
		}

		$this->headers = $headers;	
	}

	public function server ( string $key ): string|null
	{
		if ( empty ( $this->server [ $key ] ) === true )
			return null;

		return $this->server [ $key ];
	}

	private bool $parsedCookies = false;
	public function cookie ( string $key ): string|null
	{
		if ( !$this->parsedCookies )
		{
			$rawCookie = $this->header('cookie');
			if ( !$rawCookie )
				return null;

			$cookies = explode (';', $rawCookie );
			foreach ( $cookies as $cookie )
			{
				$bits = explode ('=', $cookie, 2 );
				$this->cookies [ trim ( $bits [0] ) ] = trim ( $bits [1] );
			}
		}

		if ( isset ( $this->cookies [ $key ] ) === true )
			return $this->cookies [ $key ];

		return null;
	}

	public function header ( string $key ): string|null
	{
		if ( empty ( $this->headers [ $key ] ) === true )
			return null;
			
		return $this->headers [ $key ];
	}

	public function body (): array|string|null
	{
		if ( !$this->body )
			return null;

		$data = $this->body;
		switch ( $this->header ('content-type') )
		{
			case 'application/x-www-form-urlencoded':
				parse_str( $data, $data);
				break;
			case 'application/json':
				$data = json_decode ( $data );
				break;
		}

		return $data;
	}

	public function reset (): void
	{
		$this->state = SERVE_HTTP_REQUEST_WAITING;
		$this->raw = '';
		$this->address= '';

		$this->body = null;
		$this->headers = [];
		$this->server = [];

		$this->cookies = [];
		$this->parsedCookies = false;
	}
}