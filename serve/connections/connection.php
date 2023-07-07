<?php

declare(strict_types=1);

namespace serve\connections;

use socket;

class connection
{
    protected readonly socket $socket;
    protected bool $opened = false;

    public function __construct(readonly public int $domain = AF_INET, readonly public int $type = SOCK_STREAM, readonly public int $protocol = SOL_TCP)
    {
        $this->socket = socket_create($domain, $type, $protocol);
		socket_set_nonblock ( $this->socket );
    }

    public function socket(): socket
    {
        return $this->socket;
    }

    private string $writeBuffer = '';
    public function write(string $message = null): void
    {
		if ( $message )
        	$this->writeBuffer .= $message;

        $writtenLength = socket_write($this->socket, $this->writeBuffer, strlen($this->writeBuffer));
        $this->writeBuffer = substr($this->writeBuffer, $writtenLength);

        return;
    }

    public function read(int $length = 4096): string|false
    {
		if ( $this->opened === false )
			return false;

		$message = @socket_read($this->socket, $length, PHP_BINARY_READ);

		if ( !$message )
		{
			$err = socket_last_error ( $this->socket );
			switch ( $err )
			{
				case 107:
				default:
					$this->close ();
			}

			return false;
		}
		
		return $message;
    }

	public function isOpen (): bool
	{
		return $this->opened;
	}

    public function close(): void
    {
        if ($this->opened === false)
            return;

		$this->opened = false;
        socket_close($this->socket);
    }
}
