<?php

declare(strict_types=1);

namespace serve\threads;

use Exception;
use serve\connections\unix\client;
use serve\connections\unix\server;
use serve\log;

class thread
{
	public static function spawn(callable $callback): client
	{
		static $spawned = 0;
		++$spawned;

		if ($spawned > 10) {
			exit('control your self, no more');
		}

		$connections = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		if (true === empty($connections)) {
			throw new \Exception('thread: Failed to create paired socket');
		}

		$serverSocket = array_pop($connections);
		stream_set_blocking($serverSocket, false);
		$server = new server($serverSocket);

		$clientSocket = array_pop($connections);
		stream_set_blocking($clientSocket, false);
		$client = new client($clientSocket);

		$pid = pcntl_fork();
		if (-1 === $pid) {
			throw new \Exception('thread: Failed to spawn child.');
		}

		if ($pid) {
			return $client;
		}

		log::$id = $spawned;
		$callback($server, $spawned);

		exit(0);
	}

	/**
	 * To avoid leaving zombie processes, we need to check for them, once we acknowledge their deaths, they will move on to a better place
	 * <defunct> is the result of a zombie process where the owner havnt checked up on it.
	 *
	 * @param int $flags
	 * @return int
	 * @throws Exception
	 */
	public static function wait(int $flags = WNOHANG): int
	{
		$status = 0;

		$pid = pcntl_waitpid(
			process_id: -1,
			status: $status,
			flags: $flags
		);

		if (-1 === $pid) {
			throw new \Exception('thread: Failed to wait');
		}

		if (0 === $pid) {
			return 0;
		}

		return $pid;
	}
}
