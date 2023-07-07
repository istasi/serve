<?php

declare(strict_types=1);

namespace serve\http;

class response
{
	private array $headers = [];
	private string $response;

	private bool $sent;

	public function __construct ( readonly public request $request, readonly private \serve\connections\connection $connection )
	{
		$this->reset ();
	}

	public function reset (): void
	{
		$this->sent = false;

		$this->headers = [];
		$this->response = 'HTTP/1.1 200 OK'. "\r\n";

		$this->header ('Content-Type', 'text/html');
		$this->header ('Connection', 'keep-alive');
		$this->header ('Date', date ('c'));
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

	public function body ( string $message ): bool
	{
		if ( empty ( $this->headers ['content-encoding'] ) === true )
			$this->header ('content-encoding', 'gzip');

		if ( empty ( $this->headers['content-encoding'] ) === false )
		{
			$accept = $this->request->header ('accept-encoding');
			if ( !$accept )
				$accept = '';
			$accept = str_replace (' ', '', $accept );
			$accept = explode (',', $accept );
			if ( !in_array ( haystack: $accept, needle: 'gzip' ) )
				unset ( $this->headers ['content-encoding'] );
		}

		switch ( $this->headers ['content-encoding'] ?? '' )
		{
			case 'deflate':
				$message = gzdeflate ( $message, 9 );
				break;
			case 'gzip':
				$message = gzencode ( $message );
				break;
			default:
				unset ( $this->headers ['content-encoding'] );
				break;
		}

		if ( $this->sent === false )
		{
			foreach ( $this->headers as $key => $value )
				$this->response .= $key .': '. $value ."\r\n";

			$this->response .= 'Content-Length: '. strlen ( $message ) ."\r\n";
			$this->response .= "\r\n";
		}
			
		$this->sent = true;
		$this->response .= $message;

		return true;
	}

	public function send ( string $body = null ): bool
	{
		if ( !$this->request->complete() )
			return false;

		if ( $body )
			$this->body ( $body );

		if ( empty ( $this->response ) )
			return false;

		$this->sent = true;

		$this->connection->write ( $this->response );
		$this->response = '';

		$this->reset ();
		$this->request->reset ();

		return true;
	}
}