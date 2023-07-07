<?php

declare(strict_types=1);

namespace serve;

class log
{
	public static int $id = 0;

	static public function entry ( $message )
	{
		echo '['. self::$id .']['. date ('c') .'] '. $message. PHP_EOL;
	}
}