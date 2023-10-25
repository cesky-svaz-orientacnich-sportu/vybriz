<?php

namespace App\Model;

use Nette,
	Nette\Utils;


/**
 * Repozitář kol výběrového řízení.
 */
class TerminyRepository
{
	use Nette\SmartObject;

	/** @var Nette\Database\Explorer */
	private $database;

	/** @var Nette\Database\Table\Selection */
	private $table_kola;


	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}


	/**
	 * Všechny záznamy pro dané kolo.
	 */
	public function getAllForRound($kolo_id)
	{
		return $this->database->table('terminy')->select("terminy.*, druh_id.*, terminy.id AS id")->where('kolo_id', $kolo_id);
	}


	/**
	 * Všechny záznamy.
	 */
	public function getOne($id = 0)
	{
		return $this->database->table('kola')->get((int) $id);
	}

}
