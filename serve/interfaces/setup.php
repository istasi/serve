<?php

declare(strict_types=1);

namespace serve\interfaces;

/**
 * This is a work around since im not able to do, and i dont wanna do class_uses_recursive
 * $connection instanceof traits\setup
 */
interface setup
{
	public function setup(array $options = []): array;
}