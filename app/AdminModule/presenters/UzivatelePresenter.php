<?php

namespace App\AdminModule\Presenters;

use Nette,
    Nette\Application\UI\Form,
	App\Model;

/**
 * Uzivatele presenter.
 */
class UzivatelePresenter extends BaseAdminPresenter
{

    /** @var Model\UsersRepository */
    private $usersRepository;

    public function __construct(Model\UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    /**
     * [renderDetail description]
     * @return [type]      [description]
     */
    public function renderDefault()
    {
        $this->template->uzivatele = $this->usersRepository->getAll();
    }

    /**
     * [renderDetail description]
     * @param  integer $id [description]
     * @return [type]      [description]
     */
    public function renderDetail($id = 0)
    {
        $this->template->uzivatel = $this->usersRepository->getOne($id);
    }

    /**
     * [renderDetail description]
     * @param  integer $id [description]
     * @return [type]      [description]
     */
    public function renderEdit($id = 0)
    {
        $user = $this->usersRepository->getOne($id);
        $this['userForm']->setDefaults([
            'u_id' => $id,
            'user_name' => $user['user_name'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'mail' => $user['mail'],
            'role' => $user['role'],
            'edit' => TRUE,
        ]);
        $this['userForm']['send']->caption = "Uložit změny";
        if (!$this->user->isAllowed('admin/uzivatele', 'add')) { $this['userForm']['role']->setDisabled(); }
        $this->template->uzivatel = $user;
    }


    protected function createComponentUserForm()
    {
        $form = new Form;

        $form->addText('user_name', 'Přihlašovací nick')
            ->setRequired('Zadejte přihlašovací jméno');

        $form->addText('first_name', 'Jméno')
            ->setRequired('Zadejte jméno');

        $form->addText('last_name', 'Příjmení')
            ->setRequired('Zadejte příjmení');

        $form->addText('mail', 'E-mail')
            ->setRequired('Zadejte e-mail');

        $form->addSelect('role', 'Role', ['supervisor' => 'Supervizor', 'admin' => 'Administrátor', 'komise' => 'Člen komise', 'registered' => 'Registrovaný'])->setPrompt('-')
            ->setRequired('Vyberte roli');

        $form->addHidden('edit', FALSE);
        $form->addHidden('u_id', NULL);

        $form->addSubmit('send', 'Vytvořit nového uživatele');
        $form->addProtection('Vypršela platnost formuláře.');

        // call method userFormSucceeded() on success
        $form->onSuccess[] = [$this, 'userFormSucceeded'];
        return $form;
    }

    public function userFormSucceeded($form, $values)
    {
        $data = [
            'user_name' => $values['user_name'],
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'mail' => $values['mail'],
        ];
        

        if ($this->user->isAllowed('admin/uzivatele', 'add')) {
            $data['role'] = $values['role'];
        }

        if ($values['edit'] && $values['u_id']) {
            if ($values['u_id'] && ($this->user->isAllowed('admin/uzivatele', 'add') || $this->user->id === (int) $values['u_id'])) {
                try {
                    $this->usersRepository->update((int) $values['u_id'], $data);
                } catch (Nette\Database\UniqueConstraintViolationException $e) {
                    $this->flashMessage('Nebylo možné aktualizovat údaje uživatele. Daná přezdívka je již zabrána.', 'error');
                    $this->redirect('this');              
                }

                if ($this->user->id === (int) $values['u_id']) {
                    $this->user->identity->user_name = $values['user_name'];
                    $this->user->identity->first_name = $values['first_name'];
                    $this->user->identity->last_name = $values['last_name'];
                    $this->user->identity->mail = $values['mail'];
                }
                
                $this->flashMessage('Uživatel byl úspěšně aktualizován!', 'info');
                $this->redirect(':Admin:Uzivatele:Detail', ['id' => (int) $values['u_id']]);
            }else{
                $this->flashMessage('Nemáte dostatečná oprávnění pro úpravu dat uživatelů.', 'error');
            }
        }else{
            if (!$values['u_id'] && $this->user->isAllowed('admin/uzivatele', 'add')) {
                try {
                    $this->usersRepository->add($data);
                } catch (Nette\Database\UniqueConstraintViolationException $e) {
                    $this->flashMessage('Nebylo možné přidat uživatele. Daná přezdívka je již zabrána.', 'error');
                    return;
                }
                $this->flashMessage('Uživatel byl úspěšně přidán!', 'info');
            }else{
                $this->flashMessage('Nemáte dostatečná oprávnění pro přidání nových uživatelů.', 'error');
            }
        }

        $this->redirect(':Admin:Uzivatele:');
    }
}
