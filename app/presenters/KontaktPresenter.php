<?php

namespace App\Presenters;

use Nette,
	App\Model;

/**
 * Kontakt presenter.
 */
class KontaktPresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


	public function renderDefault()
	{
		$this->template->any = 'any';
	}


}
