<?php

namespace App\AdminModule\Presenters;

use Nette,
	Nette\Utils,
	App\Model;

/**
 * Dashboard presenter.
 */
class DashboardPresenter extends BaseAdminPresenter
{

	/** @var Nette\Database\Explorer */
	private $database;

	/** @var Model\Caching\Storage */
	private $storage;

	public function __construct(
		Nette\Database\Explorer $database,
		Nette\Caching\Storage $storage
	) {
		$this->database = $database;
		$this->storage = $storage;
	}


	public function renderDefault()
	{
		$rok = $this->database->table('kola')->order('rok DESC')->limit(1)->fetch()->offsetGet('rok');
		$this->template->rok = $rok;
		$this->template->kola = $this->database->table('kola')->where('rok', $rok);
		$this->template->pocty_prihasek = $this->database->table('prihlasky')
			->alias('kolo','k')->select('k.id AS id, COUNT(*) AS cnt')
			->group('k.id')->where('stav != ?', 'draft')
			->where('k.rok', $rok)->fetchPairs('id', 'cnt');
	}



}
