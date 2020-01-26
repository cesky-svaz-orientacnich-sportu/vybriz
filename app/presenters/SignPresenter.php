<?php

namespace App\Presenters;

use Nette,
	App\Model;


/**
 * Sign in/out presenters.
 */
class SignPresenter extends BasePresenter
{


	/**
	 * Sign-in form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentSignInForm()
	{
		$form = new Nette\Application\UI\Form;
		$form->addText('username', 'Uživatelské jméno:')
			->setRequired('Prosím zadejte Vaše uživatelské jméno.');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Prosím zadejte přístupové heslo.');

		$form->addCheckbox('remember', 'Zůstat přihlášen(a)');

		$form->addSubmit('send', 'Přihlásit se')
			->setAttribute('class','btn');

		// call method signInFormSucceeded() on success
		$form->onSuccess[] = [$this, 'signInFormSucceeded'];
		return $form;
	}


	public function signInFormSucceeded($form, $values)
	{
		if ($values->remember) {
			$this->user->setExpiration('14 days', FALSE);
		} else {
			$this->user->setExpiration('20 minutes', TRUE);
		}

		try {
			$this->user->login($values->username, $values->password);
			$this->flashMessage('Uživatel byl úspěšně přihlášen.');
			$this->redirect(':Admin:Dashboard:');

		} catch (Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}


	public function actionOut()
	{
		$this->user->logout(TRUE);
		$this->flashMessage('Byl(a) jste úspěšně odhlášen(a).');
		$this->redirect(':Sign:In');
	}

}
