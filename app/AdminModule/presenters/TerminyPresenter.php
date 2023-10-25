<?php

namespace App\AdminModule\Presenters;

use Nette,
	Nette\Application\UI\Form,
	App\Model;

/**
 * Terminy presenter.
 */
class TerminyPresenter extends BaseAdminPresenter
{

	/** @var Nette\Database\Explorer */
	private $database;
	
	/** @var int @persitent */
	public $kolo_id = 0;

	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}

	public function actionManage($id = 0)
	{
		$this->kolo_id = (int) $id;
	}

	public function renderManage($id)
	{
		$kolo = $this->database->table('kola')->get($this->kolo_id);
		if(!$kolo){
			$this->flashMessage("Nelze spravovat termíny kola s daným id (".$id.").", "error");
			$this->redirect(":Admin:Dashboard:Default");            
		}
		$this->template->kolo = $kolo;
	}

	protected function createComponentTerminyForm()
	{
		$form = new Nette\Application\UI\Form;
		$form->addText('termin', 'Datum:')
			->setAttribute('class','datepicker');

		$kola = $this->database->table('kola')->select('id, concat(kolo, ". ", "kolo", ", ", rok) AS kolo')->order('id DESC')->fetchPairs('id','kolo');

		$druhy = $this->database->table('druhy')->select('id, concat(druh_zkratka, " - ", druh) AS druh')->fetchPairs('id','druh');

		$form->addSelect('kolo_id', 'Kolo:', $kola)->setPrompt('Vyberte kolo')->setDefaultValue($this->kolo_id);

		$form->addSelect('druh_id', 'Druh závodu:', $druhy)->setPrompt('Vyberte druh závodu');

		$form->addSubmit('send', 'Přidat termín')
			->setAttribute('class','btn');

		// call method terminyFormSucceeded() on success
		$form->onSuccess[] = [$this, 'terminyFormSucceeded'];
		return $form;
	}


	public function terminyFormSucceeded($form, $values)
	{
		if ($this->user->isAllowed('admin/terminy', 'add')) {
			$this->database->table('terminy')->insert($values);
			$this->flashMessage('Termín uložen.');
		}else{
			$this->flashMessage('Nemáte dostatečná oprávnění pro přidávání nových termínů.', 'error');
		}
		$this->redirect('this');
	}



	protected function createComponentSdruzeneTerminyForm()
	{
		$form = new Nette\Application\UI\Form;

		$druhy = $this->database->table('terminy')->select('terminy.id AS id, concat(terminy.id, ": ", termin, " (",terminy.kolo_id,  ") - ", druh_id.druh_zkratka, IFNULL(concat(" [", terminy.souvisejici_termin, "]"), "")) AS druh')->where('kolo_id',$this->kolo_id)->order('terminy.id')->fetchPairs('id','druh');

		$form->addRadiolist('termin_id', 'Přihlášce', $druhy);

		$form->addRadiolist('souvisejici_termin', 'Nastavit sdruženou', $druhy);

		$form->addSubmit('send', 'Uložit')
			->setAttribute('class','btn');

		// call method sdruzeneTerminyFormSucceeded() on success
		$form->onSuccess[] = [$this, 'sdruzeneTerminyFormSucceeded'];
		return $form;
	}


	public function sdruzeneTerminyFormSucceeded($form, $values)
	{
		if ($this->user->isAllowed('admin/terminy', 'add')) {
			$this->database->table('terminy')->wherePrimary($values['termin_id'])
					->update(array('souvisejici_termin' => $values['souvisejici_termin']));

			$this->flashMessage('Termín uložen.');
		}else{
			$this->flashMessage('Nemáte dostatečná oprávnění pro přidávání termínů.', 'error');
		}
		$this->redirect('this');
	}

	public function actionEdit($id = 0)
	{
		$termin = $this->database->table('terminy')->select('kolo_id')->get((int) $id);
		if (!$termin) {
			$this->flashMessage('Zadaný termín nebyl nalezen v databázi.');
			$this->redirect(':Admin:Terminy:Manage');
		}
		$this->kolo_id = $termin['kolo_id'];
	}

	protected function createComponentTerminEditForm()
	{
		$form = new Nette\Application\UI\Form;

		$termin = $this->database->table('terminy')->get((int) $this->getParam('id'));
		$sdruz_terminy = $this->database->table('terminy')->select('terminy.id AS id, concat(terminy.id, ": ", termin, " (",terminy.kolo_id,  ") - ", druh_id.druh_zkratka, IFNULL(concat(" [", terminy.souvisejici_termin, "]"), "")) AS druh')->where('kolo_id',$this->kolo_id)->order('terminy.id')->fetchPairs('id','druh');
		$kola = $this->database->table('kola')->select('id, concat(kolo, ". ", "kolo", ", ", rok) AS kolo')->order('id DESC')->fetchPairs('id','kolo');
		$druhy = $this->database->table('druhy')->select('id, concat(druh_zkratka, " - ", druh) AS druh')->fetchPairs('id','druh');

		$form->addText('termin', 'Datum:')
			->setAttribute('class','datepicker')
			->setDefaultValue($termin->termin->format('Y-m-d'));
		$form->addSelect('kolo_id', 'Kolo:', $kola)->setPrompt('Vyberte kolo')->setDefaultValue($this->kolo_id);
		$form->addSelect('druh_id', 'Druh závodu:', $druhy)->setDefaultValue($termin->druh_id)->setPrompt('Vyberte druh závodu');
		$form->addSelect('souvisejici_termin', 'Sdružený závod:', $sdruz_terminy)->setDefaultValue($termin->souvisejici_termin)->setPrompt('-');
		$form->addSubmit('send', 'Upravit termín')
			->setAttribute('class','btn');

		// call method terminyFormSucceeded() on success
		$form->onSuccess[] = [$this, 'terminEditFormSucceeded'];
		return $form;
	}

	public function terminEditFormSucceeded($form, $values)
	{
		if ($this->user->isAllowed('admin/terminy', 'edit')) {
			$this->database->table('terminy')->wherePrimary((int) $this->getParam('id'))->update($values);
			$this->flashMessage('Termín uložen.');
		}else{
			$this->flashMessage('Nemáte dostatečná oprávnění pro přidávání nových termínů.', 'error');
		}
		$this->redirect('this', ['kolo_id' => $this->kolo_id]);
	}



}
