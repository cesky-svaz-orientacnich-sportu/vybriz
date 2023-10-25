<?php

namespace App\Presenters;

use Nette,
	App\Model;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{

    /** @var Nette\Database\Explorer */
    private $database;

    public function __construct(Nette\Database\Explorer $database)
    {
        $this->database = $database;
    }


    public function renderDefault()
    {
    	$kolo = $this->database->table('kola')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();
    	$this->template->kolo = $kolo;
    }



}
