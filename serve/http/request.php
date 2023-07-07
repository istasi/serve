<?php

declare(strict_types=1);

namespace serve\http;

class request
{
	private string $raw;

	public array $headers;
	public array $server;

	public function __construct ( string $text = '' )
	{
		$this->raw = $text;
		$this->address = '';

		if ( $this->complete () )
			$this->parse ();
	}

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

		if ( $this->complete () )
			$this->parse ();
	}

	public function complete (): bool
	{
		return str_ends_with ( haystack: $this->raw, needle: "\r\n\r\n" ) === true;
	}

	public function parse (): void
	{
		$sections = explode ("\r\n\r\n", $this->raw, 2);

		$headers = array_shift ( $sections );
		$body = array_shift ( $sections );
		unset ( $sections );

		$lines = explode ("\r\n", $headers );

		$request = array_shift ( $lines );
		
		$headers = [];
		foreach ( $lines as $line )
		{
			$bits = explode (':', $line, 2);

			if ( count ( $bits ) === 2 )
				$headers [ strtolower ( trim ( $bits [0] ) ) ] = trim ( $bits [1] );
		}

		$this->headers = $headers;

		$this->server = [];
		$this->server ['remote_addr'] = $this->address;

		$bits = explode (' ', $request, 3);
		$this->server ['request_method'] = $bits [0];
		$this->server ['request_uri'] = $bits [1];
		$this->server ['server_protocol'] = $bits [2];
	}

	public function server ( string $key ): string|null
	{
		if ( empty ( $this->server [ $key ] ) === true )
			return null;

		return $this->server [ $key ];
	}

	public function header ( string $key ): string|null
	{
		if ( empty ( $this->headers [ $key ] ) === true )
			return null;
			
		return $this->headers [ $key ];
	}

	public function reset (): void
	{
		$this->raw = '';
		$this->headers = [];
		$this->server = [];
	}
}