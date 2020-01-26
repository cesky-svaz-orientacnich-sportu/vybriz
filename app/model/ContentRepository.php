<?php

namespace App\Model;

use Nette,
	Nette\Utils;


/**
 * Repozitář obsahu
 */
class ContentRepository
{
	use Nette\SmartObject;

	const T_NAME = 'content';

	/** @var Nette\Database\Context */
	private $database;


	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}


	/**
	 * Všechny záznamy.
	 */
	public function getAll()
	{
		return $this->database->table(self::T_NAME);
	}


	/**
	 * Jeden řádek podle id.
	 */
	public function getOne($id = '')
	{
		return $this->database->table(self::T_NAME)->get((string) $id);
	}


	/**
	 * Přidá nový záznam do databáze.
	 */
	public function add($values)
	{
		return $this->database->table(self::T_NAME)->insert($values);
	}


	/**
	 * Upraví záznam v databázi podle id.
	 */
	public function update($id, $values)
	{
		return $this->database->table(self::T_NAME)->wherePrimary((string) $id)->update($values);
	}


	/**
	 * Smaže záznam v databázi podle id.
	 */
	public function delete($id)
	{
		return $this->database->table(self::T_NAME)->wherePrimary((string) $id)->delete();
	}

}
