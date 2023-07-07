<?php

namespace serve;

trait events
{
	private array $events = [];

	public function on ( string $event, callable $function ): void
	{
		if ( isset ( $this->events [ $event ] ) === false )
			$this->events [ $event ] = [];

		$this->events [ $event ][] = $function;
	}

	public function triggers ( array $events = null ): array
	{
		if ( $events )
			$this->events = $events; 

		return $this->events;
	}

	public function trigger ( string $event, array $arguments = [] ): void
	{
		if ( isset ( $this->events [ $event] ) === false )
			return;

		foreach ( $this->events [ $event ] as $function )
			call_user_func_array( $function, $arguments );

		return;
	}
}