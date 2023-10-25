<?php

namespace App\Model;

use Nette,
	Nette\Utils;


/**
 * Repozitář uživatelů.
 */
class UsersRepository
{
	use Nette\SmartObject;

	/** @var Nette\Database\Explorer */
	private $database;


	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}


	/**
	 * Všechny záznamy pro dané kolo.
	 */
	public function getAll()
	{
		return $this->database->table('users')->select("id, user_name, first_name, last_name, mail, role");
	}


	/**
	 * Všechny záznamy.
	 */
	public function getOne($id = 0)
	{
		return $this->database->table('users')->select("id, user_name, first_name, last_name, mail, role")->wherePrimary((int) $id)->fetch();
	}



	/**
	 * Přidá nový záznam do databáze.
	 */
	public function add($values)
	{
		$data = [
            'user_name' => $values['user_name'],
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'mail' => $values['mail'],
            'role' => $values['role'],
            'password' => ''
        ];

		return $this->database->table('users')->insert($data);
	}



	/**
	 * Upraví záznam v databázi podle ID users.
	 */
	public function update($id, $values)
	{
		$data = [
            'user_name' => $values['user_name'],
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'mail' => $values['mail'],
        ];

        if (isset($values['role'])) {
        	$data = $data + ['role' => $values['role']];
        }

		return $this->database->table('users')->wherePrimary((int) $id)->update($data);
	}


}
