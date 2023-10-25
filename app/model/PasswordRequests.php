<?php

namespace App\Model;

use Nette,
	Nette\Security,
	Nette\Utils;


/**
 * Obnova hesel.
 */
class PasswordRequests
{
	use Nette\SmartObject;
	
	const
		USERS_TABLE = 'users',
		USERS_COLUMN_ID = 'id',
		USERS_COLUMN_EMAIL = 'mail',
		USERS_COLUMN_PASSWORD_HASH = 'password',
		REQUESTS_TABLE = 'password_reuests',
		REQUESTS_COLLUMN_USER_ID = 'user_id',
		REQUESTS_COLLUMN_TOKEN_HASH = 'token_hash',
		REQUESTS_COLLUMN_EXPIRATION = 'expiration';

	/** @var Nette\Database\Explorer */
	private $database;

	/** @var Nette\Database\Table\Selection */
	private $requests_table;

	/** @var Nette\Database\Table\Selection */
	private $users_table;

	/** @var string */
	private $token;

	/** @var string */
	private $token_hash;


	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}


	/**
	 * Tabulka Žádostí.
	 */
	public function requestsTable()
	{
		if(!isset($this->requests_table)){
			$this->requests_table = $this->database->table(self::REQUESTS_TABLE);
		}

		return $this->requests_table;
	}


	/**
	 * Tabulka uživatelů.
	 */
	public function usersTable()
	{
		if(!isset($this->users_table)){
			$this->users_table = $this->database->table(self::USERS_TABLE);
		}

		return $this->users_table;
	}


	/**
	 * Přidání záznamu/žádosti o obnovu hesla do databáze.
	 * @param  string
	 * @return array(string user_id, string token_hash, string expiration, string token, Nette\Database\Table\IRow user)
	 */
	public function addRequest($mail = '')
	{
		$user = $this->usersTable()->where(self::USERS_COLUMN_EMAIL, $mail)->fetch();

		if (!$user) {
			throw new Nette\Application\ApplicationException('Nebyl nalezen uživatel se zadaným e-mailem.');
		}

		$passwords = new Security\Passwords();
		$this->token = Utils\Random::generate(32);
		$this->token_hash = $passwords->hash($this->token);

		$props = array(
				self::REQUESTS_COLLUMN_USER_ID => $user[self::USERS_COLUMN_ID],
				self::REQUESTS_COLLUMN_TOKEN_HASH => $this->token_hash,
				self::REQUESTS_COLLUMN_EXPIRATION => Utils\DateTime::from(time()+3600)
			);

		$request_query = $this->requestsTable()->insert($props);


		return $props + array('token' => $this->token, 'user' => $user, 'request_id' => $request_query->id);
	}


	/**
	 * Změna hesla.
	 * @param  int
	 * @param  int
	 * @param  string
	 * @param  string
	 * @return bool
	 */
	public function changePassword($request_id = 0, $user_id = 0, $token, $password)
	{
		$request = $this->requestsTable()->get($request_id);
		if (!$request) {
			throw new Nette\Application\ApplicationException('Vypršela platnost tokenu. Pro obnovu hesla vyplňte frormulář znovu.');
		}

		$passwords = new Security\Passwords();
		$verification = $passwords->verify($token, $request[self::REQUESTS_COLLUMN_TOKEN_HASH]);

		if (!$verification || Utils\DateTime::from(time()) >= $request[self::REQUESTS_COLLUMN_EXPIRATION]) {
			$request->delete();
			throw new Nette\Application\ApplicationException('Vypršela platnost odkazu pro změnu hesla.');
		}

		//vymazání všech žádostí jednoho živatele
		$all_requests = $this->requestsTable()->where(self::REQUESTS_COLLUMN_USER_ID, $user_id);
		$all_requests->delete();

		$this->usersTable()->get($user_id)->update( array(self::USERS_COLUMN_PASSWORD_HASH => $passwords->hash($password)) );
	}

}
