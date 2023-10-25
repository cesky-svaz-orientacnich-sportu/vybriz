<?php

namespace App\Presenters;

use Nette,
	App\Model;

/**
 * Uploader presenter.
 */
class UploaderPresenter extends BasePresenter
{

//    /** @var Nette\Database\Explorer */
//    private $database;
//
//    public function __construct(Nette\Database\Explorer $database)
//    {
//        $this->database = $database;
//    }


	public function renderDefault()
	{
		$this->template->anyVariable = 'any value';
	}




}
