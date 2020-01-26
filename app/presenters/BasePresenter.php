<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Components\IContentControl;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

	public $mailer;

	public function injectMailer(Nette\Mail\IMailer $mailer) {
		$this->mailer = $mailer;
	}


	/** @var IContentControl @inject */
	public $contentControlFactory;

	protected function createTemplate($class = NULL)
	{
	    $template = parent::createTemplate($class);
	    $template->addFilter('json', function ($s) {
	    	$decoded = Nette\Utils\Json::decode($s);
	        return $decoded; // zkrátí text na 10 písmen
	    });

	    $template->addFilter('niceDate', function ($s) {
			$date = strtotime($s);
			$cz_days = array(1 => 'Po', 2 => 'Út', 3 => 'St', 4 => 'Čt', 5 => 'Pá', 6 => 'So', 7 => 'Ne');
			$return = $cz_days[date("N",$date)].' '.date("j.n. Y",$date);
	        return $return;
	    });



	    return $template;
	}

	/**
	 * Komponenta pro vykresleni obsahu sekci
	 */
	protected function createComponentContent()
	{
		return $this->contentControlFactory->create();
	}

}
