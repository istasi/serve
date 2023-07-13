<?php

declare(strict_types=1);

namespace serve;

class log
{
	public static int $id = 0;

	/**
	 * static public \resource $fp = STDOUT;
	 * PHP Fatal error: Uncaught TypeError: Cannot assign resource to property serve\log::$fp of type resource in
	 */
	public static $fp = STDOUT;

	/**
	 * Why php, why?
	 * it does not make sense to me when you can typehint it in function arguments function ( callable $parser )
	 * so clearly you can say a variable should be of a type, why should it matter where that variable exists
	 * 
	 * static public callable $parser;
	 * PHP Fatal error:  Property serve\log::$parser cannot have type callable in
	 */
	private static $__parser = null;
	public static function parser(callable $parser): void
	{
		self::$__parser = $parser;
	}

	public static function entry($message): void
	{
		/**
		 * PHP Fatal error:  Uncaught Error: Method name must be a string in
		 * fwrite(self::$fp, self::$parser(). $message. PHP_EOL);
		 */

		$parser = self::$__parser;
		fwrite(self::$fp, $parser(). $message. PHP_EOL);
	}
}

log::parser(function () {
	return '['. log::$id .']['. date('c') .'] ';
});
