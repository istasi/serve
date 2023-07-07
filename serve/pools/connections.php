<?php
declare(strict_types=1);

namespace serve\pools;

use socket;

class connections extends pool
{
	public function sockets ( callable $filter = null ): array
	{
		$sockets = [];

		foreach ( $this as $connection )
		{
			if ( !$connection->isOpen () )
			{
				$this->remove ( $connection );

				continue;
			}

			if ( $filter )
			{
				if ( $filter ( $connection ) )
					$sockets [] = $connection->socket ();
			}
			else
				$sockets [] = $connection->socket ();
		}

		return $sockets;
	}

	public function fromSocket ( socket $socket ): mixed
	{
		foreach ( $this as $connection )
			if ( $connection->socket () === $socket )
				return $connection;

		return null;
	}
}