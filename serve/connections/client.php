<?php

declare(strict_types=1);

namespace serve\connections;

use socket;
use serve\listener;

class client extends connection
{
	public function __construct( readonly protected socket $socket, readonly public listener $listener )
	{
		$this->opened = true;

		$this->request = new \serve\http\request ();
		$this->response = new \serve\http\response( $this->request, $this );
	}

	private \serve\http\request $request;
	private \serve\http\response $response;
	
	public function request (): \serve\http\request
	{
		return $this->request;
	}

	public function response (): \serve\http\response
	{
		return $this->response;
	}


	public function read(int $length = 4096): string|false
	{
		$address = '';
		$port = 0;

		$message = '';
		$read = parent::read ( $length );
		if ( $read )
			$this->request->append ( $read );

		if ( $this->opened )
			if ( @socket_getpeername( $this->socket, $address, $port ) )
				$message .= '#'. spl_object_id ( $this->socket ). ' '. $address .' - ';

		$this->request->address( $address );

		if ( !$this->request->complete() )
			return false;
			
		$message .= $this->request->server ('request_uri') .' '. $this->request->server ('server_protocol') .' - '. $this->request->header ('user-agent');
		return $message;
	}
}