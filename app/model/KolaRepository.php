<?php

namespace App\Model;

use Nette,
	Nette\Utils;


/**
 * Repozitář kol výběrového řízení.
 */
class KolaRepository
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	private $database;

	/** @var Nette\Database\Table\Selection */
	private $table_kola;


	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}


	/**
	 * Všechny záznamy.
	 */
	public function getAll($limit = 100, $order = NULL)
	{
		if(!isset($this->table_kola)){
			$this->table_kola = $this->database->table('kola');
		}

		if ($limit) {
			$this->table_kola->limit($limit);
		} else {
			$this->table_kola->limit(50);
		}

		if ($order) {
			$this->table_kola->order($order);
		}

		return $this->table_kola;
	}


	/**
	 * Všechny záznamy.
	 */
	public function getOne($id = 0)
	{
		return $this->database->table('kola')->get((int) $id);
	}


	/**
	 * Přidá nový záznam do databáze.
	 */
	public function add($values)
	{
		return $this->database->table('kola')->insert($values);
	}


	/**
	 * Upraví záznam v databázi podle ID kola.
	 */
	public function update($id, $values)
	{
		return $this->database->table('kola')->wherePrimary((int) $id)->update($values);
	}

}
