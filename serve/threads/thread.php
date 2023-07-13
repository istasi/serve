<?php

declare(strict_types=1);

namespace serve\threads;

use Exception;
use serve\connections\engine\client;
use serve\connections\engine\server;
use serve\log;

class thread
{
	public static array $children = [];

	public static function spawn(callable $callback): client
	{
		static $spawned = 0;
		++$spawned;

		$connections = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		if (true === empty($connections)) {
			throw new \Exception('thread: Failed to create paired socket');
		}

		$serverSocket = array_pop($connections);
		stream_set_blocking($serverSocket, false);
		$server = new server($serverSocket);
		unset($serverSocket);

		$clientSocket = array_pop($connections);
		stream_set_blocking($clientSocket, false);
		$client = new client($clientSocket);
		unset($clientSocket);

		$pid = pcntl_fork();
		if (-1 === $pid) {
			throw new \Exception('thread: Failed to spawn child.');
		}

		if ($pid) {
			cli_set_process_title('serve: master process '. getcwd() .'/');
			unset($arg);

			// This shouldn't make a different GC should be able to see these the moment we hit return
			unset($server,$connections);

			self::$children [$pid] = true;
			return $client;
		}
		unset($client,$connections);

		pcntl_async_signals(false);
		cli_set_process_title('serve: worker process');

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

		unset(self::$children [$pid]);

		return $pid;
	}

	public static function killall(): void
	{
		foreach (array_keys(self::$children) as $pid) {
			posix_kill($pid, SIGTERM);
		}
	}
}
