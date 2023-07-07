<?php

declare(strict_types=1);

namespace serve\threads;

use serve\connections\owner;
use serve\connections\worker;
use Exception;

class thread
{
	static private int $children = 0;

	static public function spawn ( callable $callback ): worker
	{
		static $spawned = 0;
		$spawned++;

		$connections = [];
		if ( !socket_create_pair ( AF_UNIX, SOCK_STREAM, 0, $connections ) )
			throw new Exception ('thread: Failed to create paired socket');

		$parentSocket = array_pop ( $connections );
		socket_set_nonblock($parentSocket);
		$parent = new owner ( $parentSocket );

		$workerSocket = array_pop ( $connections );
		socket_set_nonblock($workerSocket);
		$worker = new worker ( $workerSocket );

		$pid = pcntl_fork ();
		if ( -1 === $pid )
			throw new Exception ('thread: Failed to spawn child.');
		
		if ( $pid )
		{
			self::$children++;

			return $worker;
		}

		$callback ( $parent, $spawned );
		exit (0);
	}

	static public function wait ( int $flags = WNOHANG ): int
	{
		$status = 0;

		$pid = pcntl_waitpid (
			process_id: -1,
			status: $status,
			flags: $flags
		);

		if ( -1 === $pid )
			throw new Exception ('thread: Failed to wait');

		if ( 0 === $pid )
			return 0;

		self::$children--;

		return $pid;
	}
}