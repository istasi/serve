<?php

namespace serve\traits;

trait events
{
	private array $__preEvents = [];
	private array $events = [];

	public function on(string $event, callable $function): void
	{
		if (isset($this->__preEvents [ $event]) === true) {
			foreach ($this->__preEvents[$event] as $arguments) {
				call_user_func_array($function, $arguments);
			}
		}

		if (false === isset($this->events[$event])) {
			$this->events[$event] = [];
		}

		$this->events[$event][] = $function;
	}

	public function triggers(array $events = null): array
	{
		if ($events) {
			$this->events = $events;
		}

		return $this->events;
	}

	public function trigger(string $event, array $arguments = []): void
	{
		if (false === isset($this->events[$event])) {
			if (isset($this->__preEvents[$event]) === false) {
				$this->__preEvents[$event] = [];
			}

			$this->__preEvents[$event][] = $arguments;
			return;
		}

		foreach ($this->events[$event] as $function) {
			call_user_func_array($function, $arguments);
		}
	}
}
