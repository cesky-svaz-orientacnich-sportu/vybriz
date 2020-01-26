<?php

namespace App\Model;

use Nette,
	Nette\Utils;


/**
 * Repozitář kol výběrového řízení.
 */
class PrihlaskyRepository
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
	 * Počet záznamů kola.
	 */
	public function getCountForRound($kolo_id)
	{
		$count = $this->database->table('prihlasky')->select("count(*) AS c")->where('kolo', $kolo_id)->fetch();
		return $count ? $count['c'] : 0;
	}


	/**
	 * Všechny záznamy pro dané kolo.
	 */
	public function getAllForRound($kolo_id)
	{
		return $this->database->table('prihlasky')->select("prihlasky.*, kolo.*, druh_zavodu.*, termin.*, prihlasky.id AS id")->where('kolo_id', $kolo_id);
	}

	/**
	 * Všechny záznamy pro daný rok.
	 */
	public function getAllForYear($year = 0)
	{
		return $this->database->table('prihlasky')->select("prihlasky.*, kolo.*, druh_zavodu.*, termin.*, prihlasky.id AS id")->where('kolo.rok', $year);
	}

	/**
	 * Naprosto všechny záznamy. BEZ dalších filtrů!
	 */
	public function getAll()
	{
		return $this->database->table('prihlasky')->select("prihlasky.*, kolo.*, druh_zavodu.*, termin.*, prihlasky.id AS id");
	}


	/**
	 * Jeden záznam.
	 */
	public function getOne($id = 0)
	{
		return $this->database->table('prihlasky')->get((int) $id);
	}

	/**
	 * Nastaví OrisId dané přihlášce
	 * @param  integer $id      - ID přihlášky
	 * @param  integer $oris_id - ID ORIS
	 * @return
	 */
	public function updateOrisId($id, $oris_id)
	{
		$oris_id = $oris_id ? (int) $oris_id : NULL;
		return $this->database->table('prihlasky')->wherePrimary((int) $id)->update(['oris_id' => $oris_id]);
	}

}
