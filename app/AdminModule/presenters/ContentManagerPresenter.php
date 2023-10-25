<?php

namespace App\AdminModule\Presenters;

use Nette,
	Nette\Application\UI\Form,
	App\Model;

/**
 * Druhy presenter.
 */
class ContentManagerPresenter extends BaseAdminPresenter
{

	/** @var Model\ContentRepository @inject */
	public $contentRepository;


	public function renderDefault()
	{
		$this->template->sections = $this->contentRepository->getAll();
	}


	public function actionAdd()
	{
		if (!$this->user->isAllowed('admin/content', 'add')) {
			$this->flashMessage('Nemáte dostatečná oprávnění pro přidání druhů závodů.', 'error');
			$this->forward(':Admin:ContentManager:default');
		}
	}


	public function actionEdit($id)
	{
		if (!$this->user->isAllowed('admin/content', 'edit')) {
			$this->flashMessage('Nemáte dostatečná oprávnění pro úpravu druhů závodů.', 'error');
			$this->forward(':Admin:Druhy:');
		}
		$this->template->section = $section = $this->contentRepository->getOne($id);
		$this['sectionForm']->setDefaults([
				'id' => $section['id'],
				'title' => $section['title'],
				'content' => $section['content']
			]);
		$this['sectionForm-send']->caption = "Uložit změny";
	}


	protected function createComponentSectionForm()
	{
		$form = new Form;
		$form->addText('id', 'Identifikační jméno')
			->setRequired('Zadejte identifikační jméno.');

		$form->addText('title', 'Titulek / název sekce')
			->setRequired('Zadejte titulek / název sekce.');

		$form->addTextArea('content', 'Obsah')
			->setAttribute('class', 'texarea-wide-high tinymce');

		$form->addProtection('Vypršela platnost formuláře, vyplňte jej prosím znovu.');

		$form->addSubmit('send', 'Přidat sekci')
			->setAttribute('class','btn');

		// call method druhFormSucceeded() on success
		$form->onSuccess[] = [$this, 'sectionFormSucceeded'];
		return $form;
	}


	public function sectionFormSucceeded($form, $values)
	{
		try {
			$id = $this->getParameter('id');
			if ($id && $id != "" && ! is_null($id)) {
				$this->contentRepository->update($id, $values);
			} else {
				$this->contentRepository->add($values);
			}
		} catch (\Exception $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('this');
		}
		
		$this->redirect(':Admin:ContentManager:default');
	}

	public function handleDelete($id)
	{
		try {
			$this->contentRepository->delete($id);
			$this->flashMessage('Sekce byla smazána.');
		} catch (\Exception $e) {
			$this->flashMessage('Sekce nebyla smazána. (' . $e->getMessage() . ')', 'error');
		}
		$this->redirect(':Admin:ContentManager:default');
	}


}
