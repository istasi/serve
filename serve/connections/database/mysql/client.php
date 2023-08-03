<?php

declare(strict_types=1);

namespace serve\connections\database\mysql;

use PDO;
use PDOException;
use PDOStatement;

class client
{
	public int $id;
	public \pdo $pdo;

	public function __debugInfo()
	{
		return [];
	}

	private string $username;
	private string $password;

	public function login(string $username = null, string $password = null): void
	{
		$this->username = $username;
		$this->password = $password;
	}

	public string $database;
	public string $address;
	public int $port;

	public function connect(string $database = null, string $address = null, int $port = 3306): bool
	{
		if (!$this->username || $this->password === null) {
			return false;
		}

		if (!$database) {
			$database = $this->database;
		} else {
			$this->database = $database;
		}

		if (!$address) {
			$address = $this->address;
		} else {
			$this->address = $address;
		}

		$this->port = $port;

		if (!$database || !$address || !$port) {
			return false;
		}

		unset($this->pdo);
		if (str_starts_with(haystack: $address, needle: '/') === true) {
			$dsn = 'mysql:unix_socket=' . $address . ';dbname=' . $database;
		} else {
			$dsn = 'mysql:host=' . $address . ';port=' . $port . ';dbname=' . $database;
		}

		$this->pdo = new PDO($dsn, $this->username, $this->password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);

		return true;
	}

	public function query(string $query, array $arguments = []): array|false
	{
		$i = 0;
		do {
			try {
				$statement = $this->pdo->prepare($query);
				$statement->execute($arguments);
				
				$result = $statement->fetchAll(PDO::FETCH_ASSOC);

				return $result;
			} catch (PDOException $e) {
				$errCode = $e->getCode();
				switch ($errCode) {
					case 'HY000':
					case '2006':
					case '2013':
						if ($this->connect() === false) {
							throw $e;
						}
						break;
					default:
						throw $e;
				}
			}
		} while ($i++ < 2);

		return false;
	}
}
