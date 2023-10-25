<?php

namespace App\Model;

use Nette,
	Nette\Http\Session,
	Nette\Security,
	Nette\Utils;


/**
 * Session controler.
 */
class SessionControler
{
	use Nette\SmartObject;

	/** @var string */
	private $session;

	/** @var string */
	public $prihlaskaId;

	/** @var string */
	public $section;


	public function __construct(Session $session)
	{
		$this->session = $session;
	}


	/**
	 * Předání ID přihlášky do parametru třídy
	 */
	public function setId($pId)
	{
		$this->prihlaskaId = $pId;
	}




	/**
	 * Předání sekce do parametru třídy a vrácení proměnné 'prihlasky' ze sekce
	 */
	public function getPrihlaskySection()
	{
		$this->section = $this->session->getSection('prihlasky_sekce');

		//nastavení expirace
		$this->section->setExpiration('2 days');

		return $this->section;
	}




	/**
	 * vrací pole všech přihlášek s daným stavem
	 */
	public function prihlaskyByState($state)
	{
		$arr = array();
		$sec_prihlasky = $this->getPrihlaskySection();

		if(!$sec_prihlasky->prihlasky){ return $arr; }

		foreach ($sec_prihlasky->prihlasky as $key => $data) {
			if($data['state'] === $state){
				$arr[] = $data;
			}
		}

		return $arr;
	}

	/**
	 * Změna stavu
	 */
	public function changeState($state)
	{
		$sec_prihlasky = $this->getPrihlaskySection();
		$sec_prihlasky->prihlasky['prihlaska-'.$this->prihlaskaId]['state'] = $state;
	}

	/**
	 * Změna posledního kroku
	 */
	public function increaseStep($step, $pId = NULL)
	{
		//nastavení ID přihlášky buď z parametru třidy nebo předáním proměnné
		$prihlaska_id = $pId ? $pId : $this->prihlaskaId;

		//vrátí sekci SESSION
		$sec_prihlasky = $this->getPrihlaskySection();

		//vybere krok ze sekce SESSION
		$highest_step = isset($sec_prihlasky->prihlasky['prihlaska-'.$this->prihlaskaId]['step']) ? $sec_prihlasky->prihlasky['prihlaska-'.$this->prihlaskaId]['step'] : 0;

		//nastaví nový nejvyšší krok
		$sec_prihlasky->prihlasky['prihlaska-'.$prihlaska_id]['step'] = $highest_step < $step ? $step : $highest_step;
	}

	/**
	 * Změna posledního kroku
	 */
	public function setStep($step, $pId = NULL)
	{
		//nastavení ID přihlášky buď z parametru třidy nebo předáním proměnné
		$prihlaska_id = $pId ? $pId : $this->prihlaskaId;

		//vrátí sekci SESSION
		$sec_prihlasky = $this->getPrihlaskySection();

		//nastaví nový nejvyšší krok
		if($prihlaska_id){
			$sec_prihlasky->prihlasky['prihlaska-'.$prihlaska_id]['step'] = $step;
		}
	}

	/**
	 * Vrací poslední krok dané přihlášky
	 */
	public function highestStep($pId = NULL)
	{
		//nastavení ID přihlášky buď z parametru třidy nebo předáním proměnné
		$prihlaska_id = $pId ? $pId : $this->prihlaskaId;

		//vrátí sekci SESSION
		$sec_prihlasky = $this->getPrihlaskySection();

		//vrátí aktuální nejvyšší krok
		return $sec_prihlasky->prihlasky['prihlaska-'.$prihlaska_id]['step'];
	}

	/**
	 * Předání sekce do parametru třídy a vrácení proměnné 'prihlasky' ze sekce
	 */
	public function clearPrihlaskySection($pId = NULL)
	{
		$section = $this->session->getSection('prihlasky_sekce');

		if($pId){
			unset($section->prihlasky['prihlaska-'.$pId]);
		}else{
			unset($section->prihlasky);
		}
	}


	/**
	 * Vymaže celou SESSION
	 */
	public function unsetAll()
	{
		$section = $this->session->getSection('prihlasky_sekce');
		foreach ($section as $key => $value) {
			unset($section[$key]);
		}
	}





	/**
	 * Vytvoření nového klíče
	 */
	public function newHash($pId = NULL)
	{
		if(!$pId && !$this->prihlaskaId){
			throw new Nette\Application\ApplicationException('Není nastaveno ID přihlášky');
		}

		$prihlaskaId = !is_null($pId) ? $pId : $this->prihlaskaId;

		//vygenerování náhodného řetězce
		$passwords = new Security\Passwords();
		$hash = Utils\Random::generate(6,'0-9a-z');
		$hash_key = $passwords->hash($hash);

		//sekce přihlášek ze session
		$sec_prihlasky = $this->getPrihlaskySection();

		//přidání dat do session
		$sec_prihlasky->prihlasky['prihlaska-'.$prihlaskaId] = array(
				'id' 		=> $prihlaskaId,
				'hash' 		=> $hash,
				'hash_key' 	=> $hash_key,
				'state' 	=> 'draft'
			);

		//vrácení hash
		return $hash;
	}

	/**
	 * Ověření klíče
	 */
	public function verifyHash($hash)
	{
		//sekce přihlášek ze session
		$sec_prihlasky = $this->getPrihlaskySection();

		//ověření hashe a klíče ze session
		$passwords = new Security\Passwords();
		$verification = isset($sec_prihlasky->prihlasky['prihlaska-'.$this->prihlaskaId]['hash_key']) ? $passwords->verify($hash, $sec_prihlasky->prihlasky['prihlaska-'.$this->prihlaskaId]['hash_key']) : false;

		return $verification;
	}

	/**
	 * Nastavení poslední žádosti pro účely kopírování dat do nové přihlášky (např. se změnou preference)
	 */
	public function setLastApplication(array $prihlasky_data = NULL)
	{
		$sec_prihlasky = $this->getPrihlaskySection();

		//pole IDs ze session
		$pIds = array();
		if($sec_prihlasky->lastApplication){
			foreach ($sec_prihlasky->lastApplication as $p) {
				$pIds[] = $p['id'];
			}
		}

		/**
		* pokud již ID nebylo zmíněno dříve, uloží data poslední žádosti
		*/
		if( !in_array($prihlasky_data[0]['id'], $pIds) ){
			$sec_prihlasky->lastApplication = $prihlasky_data;
		}
	}


	/**
	 * Vrátí data poslední žádosti
	 */
	public function getLastApplication(array $pIds = NULL)
	{
		$sec_prihlasky = $this->getPrihlaskySection();
		return $sec_prihlasky->lastApplication;
	}


	/**
	 * Vrátí data poslední žádosti
	 */
	public function removeLastApplication()
	{
		$sec_prihlasky = $this->getPrihlaskySection();
		unset($sec_prihlasky->lastApplication);
	}


}
