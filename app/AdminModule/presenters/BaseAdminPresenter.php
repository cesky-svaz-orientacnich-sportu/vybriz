<?php

namespace App\AdminModule\Presenters;

use	App\Model;
use App\Helpers\AssetsRevisioning;
use Nette;

/**
 * Base presenter for all application presenters in admin module.
 */
abstract class BaseAdminPresenter extends Nette\Application\UI\Presenter
{
    public function startup()
    {
        parent::startup();

        if (!$this->user->isLoggedIn() && !$this->user->isInRole('komise') && !$this->user->isInRole('supervisor')) {
            $this->flashMessage('Pro přístup na dané stránky je nuté se přihlásit.', 'info');
            $this->redirect(':Sign:In');
        }
    }

    public function beforeRender(): void
	{
		parent::beforeRender();

		$latte = $this->template->getLatte();
		$latte->addExtension(new \Latte\Essential\RawPhpExtension);
		$latte->addFunction('asset', AssetsRevisioning::getAssetRevisionFunction());
	}
}
