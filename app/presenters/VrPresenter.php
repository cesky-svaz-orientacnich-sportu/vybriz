<?php

namespace App\Presenters;

use Nette,
	App\Model;

/**
 * Vr presenter.
 */
class VrPresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


    public function renderFaq()
    {
        $this->template->any = 'any';
    }


    public function renderPodminky()
    {
        $this->template->any = 'any';
    }


    public function renderTerminy()
    {
        $this->template->terminy = $this->database->table('terminy')->select('terminy.termin, druh_id.druh, terminy.souvisejici_termin')->where('kolo_id',3);
    }


}
