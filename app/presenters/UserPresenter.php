<?php

namespace App\Presenters;

use Nette,
	Nette\Application\UI\Form,
	App\Model;

/**
 * User presenter.
 */
class UserPresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

	/** @var Model\PasswordRequests */
	private $requests;

    public function __construct(Nette\Database\Context $database, Model\PasswordRequests $requests)
    {
        $this->database = $database;
        $this->requests = $requests;
    }


	public function renderDefault()
	{

	}


	public function renderPasswordRequest($request_id = NULL, $user_id = NULL, $token = NULL)
	{
		$this->template->proceeded = ($request_id != NULL && $user_id != NULL && $token != NULL) ? TRUE : FALSE;
	}




	/**
	*	Formulář pro zaslání emailu na obnovu hesla
	*/

	public function createComponentAddRequestForm()
	{
		$form = new Form;

		$form->addText('mail','Zadejte váš e-mail:');

		$form->addSubmit('send','Odeslat žádost');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->onSuccess[] = array($this, 'addRequestFormSubmitted');

	    return $form;
	}

	public function addRequestFormSubmitted($form, $values)
	{
		try {
			$request = $this->requests->addRequest($values['mail']);
			$user = $request['user'];

			//odeslání mailu
			$template = $this->createTemplate();
			$template->setFile(__DIR__ . '/templates/components/passwordRequests.mail.latte');
			$template->request = array(
				'request_id' => $request['request_id'],
				'user_id' => $request['user_id'],
				'token' => $request['token']
			);

			$mail = new Nette\Mail\Message;
			$mail->setFrom('Výběrové řízení OB <vybriz@orientacnisporty.cz>')
				->addTo($user->mail)
				->setHtmlBody($template);

			$this->mailer->send($mail);

			$this->flashMessage('žádost o obnovu hesla byla úspěšně odeslána. Zkontrolujte si prosím e-mailovou schránku.');
			$this->redirect('this');

		} catch (Nette\Application\ApplicationException $e) {
			$form->addError($e->getMessage());
		}

	}



	/**
	*	Formulář zadání nového hesla
	*/

	public function createComponentChangePassForm()
	{
		$form = new Form;

		$form->addPassword('password','Nové heslo:')
			->setRequired(true)
    		->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 3);
		$form->addPassword('password_check','Heslo znovu:');

		$form->addSubmit('send','Změnit heslo');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->onSuccess[] = array($this, 'changePassFormSubmitted');

	    return $form;
	}

	public function changePassFormSubmitted($form, $values)
	{

		if($values['password'] != $values['password_check']){
			$form->addError('Hesla se neshodují.');
		}elseif($values['password'] == '' || $values['password'] == NULL){
			$form->addError('Zadejte heslo.');
		}else{

			try {
				$user_id = $this->getParameter('user_id');
				$token = $this->getParameter('token');
				$request_id = $this->getParameter('request_id');

				$this->requests->changePassword($request_id, $user_id, $token, $values['password']);

				$this->flashMessage('Heslo bylo změněno.');
				$this->redirect('Sign:in');
			} catch (Nette\Application\ApplicationException $e) {
				$this->flashMessage($e->getMessage(), 'error');
				$this->redirect('User:PasswordRequest');
			}


		}
		
	}

}
