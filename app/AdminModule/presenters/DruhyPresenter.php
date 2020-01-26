<?php

namespace App\AdminModule\Presenters;

use Nette,
    Nette\Application\UI\Form,
	App\Model;

/**
 * Druhy presenter.
 */
class DruhyPresenter extends BaseAdminPresenter
{

    const SORT_ASC = "ASC",
          SORT_DESC = "DESC";

    const COLUMN_DRUH = "druh",
          COLUMN_ZKR = "druh_zkratka",
          COLUMN_ID = "id";

    /** @var Model\DruhyRepository */
    private $druhyRepository;


    public function __construct(Model\DruhyRepository $druhyRepository)
    {
        $this->druhyRepository = $druhyRepository;
    }


    public function renderDefault($order = NULL, $sort = NULL)
    {
        $this->template->druhy = $this->druhyRepository->getAll();
        if (in_array($order, [self::COLUMN_DRUH, self::COLUMN_ZKR, self::COLUMN_ID]) && in_array($sort, [self::SORT_ASC, self::SORT_DESC])) {
            $this->template->druhy->order($order . " " . $sort);
        }
        $this->template->order = $order;
        $this->template->sort = $sort;
    }


    public function actionNovy()
    {
        if (!$this->user->isAllowed('admin/druhy', 'add')) {
            $this->flashMessage('Nemáte dostatečná oprávnění pro přidání druhů závodů.', 'error');
            $this->forward(':Admin:Druhy:');
        }
    }


    public function actionEdit($id)
    {
        if (!$this->user->isAllowed('admin/druhy', 'edit')) {
            $this->flashMessage('Nemáte dostatečná oprávnění pro úpravu druhů závodů.', 'error');
            $this->forward(':Admin:Druhy:');
        }
        $druh = $this->druhyRepository->getOne($id);
        $this['druhForm']->setDefaults([
                'd_id' => $druh['id'],
                'druh' => $druh['druh'],
                'druh_zkratka' => $druh['druh_zkratka'],
                'edit' => TRUE,
            ]);
        $this['druhForm-send']->caption = "Uložit změny";
    }


    protected function createComponentDruhForm()
    {
        $form = new Form;
        $form->addText('druh', 'Druh závodu:')->setRequired('Zadejte druh závodu.');
        $form->addText('druh_zkratka', 'Zkratka:')->setRequired('Zadejte zkratku.');

        $form->addHidden('edit', FALSE);
        $form->addHidden('d_id');

        $form->addProtection('Vypršela platnost formuláře, vyplňte jej prosím znovu.');

        $form->addSubmit('send', 'Přidat druh')
            ->setAttribute('class','btn');

        // call method druhFormSucceeded() on success
        $form->onSuccess[] = [$this, 'druhFormSucceeded'];
        return $form;
    }


    public function druhFormSucceeded($form, $values)
    {
        #save date
        $data = [
            'druh' => $values['druh'],
            'druh_zkratka' => $values['druh_zkratka'],
        ];

        if ($values['edit'] == TRUE) {
            if ($values['d_id'] && $this->user->isAllowed('admin/druhy', 'edit')) {
                $this->druhyRepository->update((int) $values['d_id'], $data);
                $this->flashMessage('Druh byl úspěšně urpaven.', 'info');
            }else{
                $this->flashMessage('Došlo k chybě a druh závodu nemohl být upraven.', 'error');
            }
        }else{
            if (!$values['d_id'] && $this->user->isAllowed('admin/druhy', 'add')) {
                $this->druhyRepository->add($data);
                $this->flashMessage('Druh byl úspěšně přidán.', 'info');
            }else{
                $this->flashMessage('Došlo k chybě a nový druh závodu nemohl být přidán.', 'error');
            }
        }

        $this->redirect(':Admin:Druhy:');
    }



}
