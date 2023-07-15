<?php

declare(strict_types=1);

namespace serve\traits;

trait setup
{
	protected array $options = [];

	public function setup(array $options = []): array
	{
		$this->options = array_replace($this->options, $options);

		return $this->options;
	}
}
