<?php

declare(strict_types=1);

namespace serve;

use serve\connections\connection;

class listener extends connection
{
	use events;

	public function __construct ( readonly public string $address = '127.0.0.1', readonly public int $port = 8000 )
	{
		parent::__construct (
			domain: AF_INET,
			type: SOCK_STREAM,
			protocol: SOL_TCP
		);

		$this->backlog = 8;
		
		$this->bind ( $address, $port );
	}

	private int $backlog;
    public function backlog(int $amount = null): int
    {
        if ($amount) {
            $this->backlog = $amount;
        }

        return $this->backlog;
    }

    public function bind(string $address, int $port)
    {
		socket_set_option ( $this->socket, SOL_SOCKET, SO_REUSEADDR, 1 );

        socket_bind($this->socket, $address, $port);
		socket_listen ( $this->socket, $this->backlog );

        $this->opened = true;
    }

	public function accept (): connections\client|false
	{
		$socket = socket_accept ( $this->socket );
		if ( !$socket )
			return false;

		$client = new connections\client ( $socket, $this );

		return $client;
	}
}