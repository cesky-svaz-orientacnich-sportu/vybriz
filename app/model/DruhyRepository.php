<?php

namespace App\Model;

use Nette,
	Nette\Utils;


/**
 * Repozitář druhů závodů VŘ.
 */
class DruhyRepository
{
	use Nette\SmartObject;

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
		return $this->database->table('druhy');
	}


	/**
	 * Všechny záznamy.
	 */
	public function getOne($id = 0)
	{
		return $this->database->table('druhy')->get((int) $id);
	}


	/**
	 * Přidá nový záznam do databáze.
	 */
	public function add($values)
	{
		return $this->database->table('druhy')->insert($values);
	}


	/**
	 * Upraví záznam v databázi podle ID druhy.
	 */
	public function update($id, $values)
	{
		return $this->database->table('druhy')->wherePrimary((int) $id)->update($values);
	}

}
