<?php

declare(strict_types=1);

namespace serve;

class log
{
	static public int $id = 0;
	static public $fp = STDOUT;
	
	static public function entry ( $message ): void
	{
		fwrite (self::$fp, '['. self::$id .']['. date ('c') .'] '. $message. PHP_EOL);
	}
}
