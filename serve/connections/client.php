<?php

declare(strict_types=1);

namespace serve\connections;

use socket;
use serve\listener;
use Throwable;

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
		$message = '';

		$address = '';
		$port = 0;

		if ( $this->opened && @socket_getpeername( $this->socket, $address, $port ) )
		{
			$message .= '#'. spl_object_id ( $this->socket ). ' '. $address .' - ';
			$this->request->address( $address );
		}
		
		$read = parent::read ( $length );
		if ( $read )
		{
			$this->request->append ( $read );

			if ( $this->request->complete() )
			{
				$message .= $this->request->server ('request_uri') .' '. $this->request->server ('server_protocol') .' - '. $this->request->header ('user-agent');
				$response = $this->response();

				try
				{
					$this->listener->trigger ('request', [ 'request' => $this->request (), 'response' => $response ]);
				}
				catch ( Throwable $e )
				{
					$response->send ('<h1>'. $e->getMessage () .'</h1><h2>'. $e->getFile () .':'. $e->getLine () .'</h2><pre>'. $e->getTraceAsString() .'</pre>' );
				}
				
				return $message;
			}
		}

		return false;
	}
}