<?php

namespace App\Model;

use Nette;
use Nette\Utils\Strings;
use Nette\Security\Passwords;


/**
 * Users management.
 */
class UserManager implements Nette\Security\Authenticator
{
	use Nette\SmartObject;
	
	const
		TABLE_NAME = 'users',
		COLUMN_ID = 'id',
		COLUMN_NAME = 'user_name',
		COLUMN_EMAIL = 'mail',
		COLUMN_PASSWORD_HASH = 'password',
		COLUMN_ROLE = 'role';


	/** @var Nette\Database\Explorer */
	private $database;


	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}


	/**
	 * Performs an authentication.
	 * @return Nette\Security\SimpleIdentity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(string $user, string $password): Nette\Security\IIdentity
	{
		$row = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_NAME, $user)->fetch();

		$passwords = new Passwords();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);

		} elseif (!$passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);

		} elseif ($passwords->needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
			$row->update(array(
				self::COLUMN_PASSWORD_HASH => $passwords->hash($password),
			));
		}

		$arr = $row->toArray();
		unset($arr[self::COLUMN_PASSWORD_HASH]);
		return new Nette\Security\SimpleIdentity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
	}
}
