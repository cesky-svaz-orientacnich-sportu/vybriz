<?php

namespace App\Model;

use Nette;
use Nette\Security\Passwords;
use Nette\Utils\Random;
use Nette\Utils\DateTime;


/**
 * Repozitář přístupu k zobrazení odeslaných přihlášek podle mailu
 */
class AccessControler
{
	use Nette\SmartObject;

	const T_NAME = 'access_requests';
	const SECTION_NAME = 'access-request-session';

	/** @var Nette\Database\Explorer */
	private $database;

	/** @var Nette\Http\SessionSection */
	private $section;

	public function __construct(Nette\Database\Explorer $database, Nette\Http\Session $session)
	{
		$this->database = $database;
        $this->section = $session->getSection(self::SECTION_NAME);
        $this->section->setExpiration('0');
		#$this->session = $session;
	}


	/**
	 * Všechny záznamy.
	 * @return array
	 */
	public function makeRequest($mail)
	{
		$this->cleanDatabase();
		$passwords = new Passwords();
		$token = Random::generate();
		$token_hash = $passwords->hash($token);
		$now = DateTime::from('now');
		$expiration = DateTime::from('+30 minutes');

		if ($this->database->table('prihlasky')->where('mail', $mail)->count() <= 0) {
			throw new ZeroRecordsException("Přes tento e-mail nebyla poslána žádná přihláška.", 1);
		}

		$row = $this->database->table(self::T_NAME)->insert([
			'mail' => $mail,
			'token_hash' => $token_hash,
			'expiration' => $expiration
		]);		

		return [
			'id' => $row->id,
			'mail' => $mail,
			'token' => $token,
			'now' => $now,
			'expiration' => $expiration
		];
	}

	/**
	 * Overi mail a token
	 * ulozi pristup do session
	 * @return bool
	 */
	public function verify($id = 0, $mail = '', $token = '')
	{
		$request = $this->database->table(self::T_NAME)
			->where('id ? AND mail ? AND expiration >= ?', $id, $mail, DateTime::from('now'))
			->fetch();

		if (!$request) {
			return FALSE;
		} elseif (!$passwords->verify($token, $request['token_hash'])) {
			return FALSE;
		}

		$request->delete();
		$this->cleanDatabase();
		
		try {
			$this->gainAccess($id, $mail, $token);
			return TRUE;
		} catch (\Exception $e) {
			return FALSE;			
		}

		return FLASE;
	}

	/**
	 * Vytvori povoleni v session k pristupu k prihlaskam
	 */
	private function gainAccess($id = 0, $mail = '', $token = '')
	{
		$this->section->access = [
			'access' => TRUE,
			'id' => $id,
			'mail' => $mail,
			'token' => $token,
			'timestamp' => DateTime::from('now')
		];
	}

	/**
	 * Smaze expirovane pozadavky v databazi
	 */
	private function cleanDatabase()
	{
		$this->database->table(self::T_NAME)
			->where('expiration < ?', DateTime::from('now'))
			->delete();
	}

	/**
	 * Vrati informaci ulozene v session
	 * @return array|NULL
	 */
	public function getAccessData()
	{
		return $this->section->access;
	}

	/**
	 * Smaze informaci ulozenou v session
	 */
	public function deleteAccessData()
	{
		unset($this->section->access);
		$this->cleanDatabase();
	}



}


class ZeroRecordsException extends \Exception
{}
