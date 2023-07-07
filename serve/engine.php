<?php

declare(strict_types=1);

namespace serve;

use serve\pools\pool;
use serve\threads\thread;
use serve\connections\owner;
use serve\connections\worker;

use Throwable;
use Exception;
use serve\exceptions\kill;

define ( 'ENGINE_WORKER_READY', '1' );
define ( 'ENGINE_WORKER_ACCEPT', '2' );
define ( 'ENGINE_WORKER_DENY', '3' );
define ( 'ENGINE_WORKER_DIE', '4' );

spl_autoload_register( function ( $class )
{
	$file = ltrim ( $class, '\\');
	$file = str_replace(
		search: '\\',
		replace: '/',
		subject: $file
	);

	$file = $file .'.php';
	$file = __DIR__ .'/../'. $file;

	if ( file_exists ( $file ) )
		require_once $file;
} );

class engine extends \serve\pools\connections
{
	use events;

	private pool $includedFiles;

	public function __construct ( private array $config = [] )
	{
		parent::__construct ( size: -1 );

		$defaultConfig = [
			'workers' => 4, 		// Amount of processes that handles incoming requests, this means parallel processing.
			'internal_delay' => 1 	// Seconds to wait for socket connections before checking other things, such as if file are modified
		];

		foreach ( $defaultConfig as $key => $value )
			if ( isset ( $this->config [ $key ] ) === false )
				$this->config [ $key ] = $value;
	}

	public function setup ( array $config = [] ): void
	{
		foreach ( $config as $key => $value )
		{
			switch ( $key )
			{
				case 'internal_delay':
					if ( is_numeric ( $value ) === false )
						break;

					$this->config [ $key ] = (int) $value;
					break;
				case 'parent':
						$this->add ( $value );

				case 'workers':
				default:
					$this->config [ $key ] = $value;
			}
		}
	}

	public function run (): void
	{
		$config = array_merge ( [], $this->config );
		$workers = (int) $config ['workers'] ?? 4;

		if ( empty ( $config ['internal_delay'] ) === true )
			$config ['internal_delay'] = 1;

		if ( $workers < 1 )
			throw new Exception ('Too few workers configured');

		if ( !isset ( $config ['owner'] ) )
			$this->spawnChildren ( $workers, $config );

		do
		{
			if ( isset ( $config ['owner'] ) ) 
				$config ['owner']->send ();
			else 
			{
				$workerCount = 0;
				foreach ( $this as $connection )
				{
					if ( $connection instanceof worker )
					{
						$workerCount++;
						if ( $this->watch ( $connection->files () ) === true )
							break;
					}
				}

				if ( $workerCount < $workers )
				{
					$amount = $workers - $workerCount;
					if ( $amount > 0 )
						$this->spawnChildren ( $amount, $config );
				}
			}

			$read = $this->sockets ( function ( $connection ) use ( $config )
			{
				if ( isset ( $config ['owner'] ) === false )
					if ( $connection instanceof listener )
						return false;

				return true;
			});

			if ( empty ( $read ) === true )
				if ( isset ($config['owner']) === false )
				{
					sleep ($config ['internal_delay']);
					continue;
				}
				else
					break;

			$write = [];
			foreach ( $this as $connection )
				if ( !$connection->writeBufferEmpty () )
					$write [] = $connection->socket;

			$exception = [];

			$changes = socket_select ( read: $read, write: $write, except: $exception, seconds: $config ['internal_delay'], microseconds: 0 );
			if ( $changes === 0 )
				continue;

			foreach ( $write as $connection )
				$connection->write ();

			foreach ( $read as $connection )
			{
				$connection = $this->fromSocket ( $connection );

				if ( $connection instanceof listener )
				{
					$client = $connection->accept ();
					if ( $client )
						$this->add ( $client );

					continue;
				}

				$message = $connection->read ();
				if ( $message )
					log::entry( get_class ( $connection ) .': "'. print_r ( $message, true ) .'"');
			}
		}
		while ( 1 );

		if ( isset ( $config ['owner'] ) )
			$config ['owner']->write ( ENGINE_WORKER_DIE );
	}

	private $watchingFiles = [];

	private function watch ( array $files = [] ): bool
	{
		// These we cannot update without restarting
		$ownerFiles = get_included_files ();

		$cycle = [];
		foreach ( $files as $file )
		{
			if ( in_array ( haystack: $ownerFiles, needle: $file ) )
				continue;
			
			$time = filemtime ( $file );
			if ( isset ( $this->watchingFiles [ $file ] ) === true && $this->watchingFiles [ $file ] < $time )
				$cycle [] = $file;

			$this->watchingFiles [ $file ] = $time;
		}

		if ( empty ( $cycle ) === false )
		{
			foreach ( $cycle as $file )
				foreach ( $this as $connection )
					if ( $connection instanceof worker && in_array ( haystack: $connection->files (), needle: $file ) )
						$connection->die ();
			
			return true;
		}

		return false;
	}

	private function spawnChildren ( int $workers, array $config ): void
	{
		$original = $this;

		for ( $i = 0; $i < $workers; $i++ )
		{
			$child = thread::spawn ( function ( owner $owner, int $id ) use ( $config, $original )
			{
				log::$id = $id;

				$engine = new engine ( $config );
				$engine->triggers ( $original->triggers () );

				$engine->setup ([
					'owner' => $owner
				]);

				foreach ( $original as $connection )
					if ( $connection instanceof listener )
						$engine->add ( $connection );

				$engine->add ( $owner );
				$engine->trigger ('worker_start');

				try
				{	$engine->run (); }
				catch ( kill $exception )
				{
					foreach ( $this as $connection )
						$connection->close ();

					$engine->trigger ('worker_end');
				}
			} );

			if ( $child )
				$this->add ( $child );
		}			
	}
}