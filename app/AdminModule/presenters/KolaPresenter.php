<?php

namespace App\AdminModule\Presenters;

use Nette,
    Nette\Application\UI\Form,
	App\Model;

/**
 * Kola presenter.
 */
class KolaPresenter extends BaseAdminPresenter
{

    /** @var Model\KolaRepository */
    private $kolaRepository;

    /** @var Model\PrihlaskyRepository */
    private $prihlaskyRepository;

    /** @var Model\TerminyRepository */
    private $terminyRepository;

    /** @var Model\Caching\Storage */
    private $storage;

    public function __construct(Model\KolaRepository $kolaRepository, Model\PrihlaskyRepository $prihlaskyRepository, Model\TerminyRepository $terminyRepository, Nette\Caching\IStorage $storage)
    {
        $this->kolaRepository = $kolaRepository;
        $this->prihlaskyRepository = $prihlaskyRepository;
        $this->terminyRepository = $terminyRepository;
        $this->storage = $storage;
    }

    /**
     * Promaže cachované šablony, aby se mohly znovu načíst z databáze
     */
    public function handleRefreshAll($id = 0)
    {
        $cache = new Nette\Caching\Cache($this->storage);
        $c = $cache->clean(array(
            Nette\Caching\Cache::TAGS => array('prihlasky/' . (int) $id),
        ));
        $this->flashMessage('Výpis byl znovu načten z databáze.');
        $this->redirect('this');
    }

    /**
     * [renderDetail description]
     * @param  integer $id [description]
     * @return [type]      [description]
     */
    public function renderDefault()
    {
        //$storage = new Nette\Caching\Storages\FileStorage($this->context->parameters['tempDir']);
        //$cache = new Nette\Caching\Cache($storage);
        //$cache->clean(array(
        //    Nette\Caching\Cache::TAGS => ["terminy/1"],
        //));
        //exit(dump($cache));
        $this->template->kola = $this->kolaRepository->getAll();
    }

    /**
     * [renderDetail description]
     * @param  integer $id [description]
     * @return [type]      [description]
     */
    public function renderDetail($id = 0)
    {
        $this->template->kolo = $this->kolaRepository->getOne($id);
        $this->template->terminy = $this->terminyRepository->getAllForRound($id);
        $this->template->prihlasky = $this->prihlaskyRepository->getAllForRound($id);
    }

    /**
     * [renderDetail description]
     * @param  integer $id [description]
     * @return [type]      [description]
     */
    public function renderEdit($id = 0)
    {
        $kolo = $this->kolaRepository->getOne($id);
        $this['koloForm']->setDefaults([
            'rok' => $kolo['rok'],
            'kolo' => $kolo['kolo'],
            't_od' => $kolo['od']->format("Y-m-d"),
            't_do' => $kolo['do']->format("Y-m-d"),
            'edit' => TRUE,
            'k_id' => $id,
            'podminky_cislo_sdeleni' => $kolo['podminky_cislo_sdeleni'],
            'podminky_link' => $kolo['podminky_link'],
            'vysledky_cislo_sdeleni' => $kolo['vysledky_cislo_sdeleni'],
            'vysledky_link' => $kolo['vysledky_link'],
        ]);
        $this['koloForm']['send']->caption = "Uložit změny";
        $this->template->kolo = $kolo;
    }


    protected function createComponentKoloForm()
    {
        $form = new Form;

        $form->addText('rok', 'Rok')
            ->setType('number')
            ->setDefaultValue(date("Y", time())+1)
            ->setRequired('Zadejte rok');

        $form->addText('kolo', 'Kolo (název)')
            ->setRequired('Zadejte název kola');

        $form->addText('t_od', 'Od')
            ->setAttribute('class', 'datepicker')
            ->addRule(Form::PATTERN, 'Musí být datum!', '(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))')
            ->setRequired('Zadejte datum od');

        $form->addText('t_do', 'Do')
            ->setAttribute('class', 'datepicker')
            ->addRule(Form::PATTERN, 'Musí být datum!', '(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))')
            ->setRequired('Zadejte datum od');

        $form->addText('podminky_cislo_sdeleni', 'Podmínky - číslo sdělení')->setAttribute('placeholder', '23/2012');
        $form->addText('podminky_link', 'Podmínky (URL)')->setAttribute('placeholder', 'https://www.orientacnibeh.cz/upload/dokumenty/sekce-ob/__________.pdf');
        $form->addText('vysledky_cislo_sdeleni', 'Výsledky - číslo sdělení')->setAttribute('placeholder', '5/2013');
        $form->addText('vysledky_link', 'Výsledky (URL)')->setAttribute('placeholder', 'https://www.orientacnibeh.cz/upload/dokumenty/sekce-ob/__________.pdf');

        $form->addHidden('edit', FALSE);
        $form->addHidden('k_id', NULL);

        $form->addSubmit('send', 'Vytvořit nové kolo');
        $form->addProtection('Vypršela platnost formuláře.');

        // call method recoveryFormSucceeded() on success
        $form->onSuccess[] = [$this, 'koloFormSucceeded'];
        return $form;
    }

    public function koloFormSucceeded($form, $values)
    {
        $data = [
            'rok' => $values['rok'],
            'kolo' => $values['kolo'],
            'od' => $values['t_od'],
            'do' => $values['t_do'],
            'podminky_cislo_sdeleni' => $values['podminky_cislo_sdeleni'],
            'podminky_link' => $values['podminky_link'],
            'vysledky_cislo_sdeleni' => $values['vysledky_cislo_sdeleni'],
            'vysledky_link' => $values['vysledky_link'],
        ];

        if ($values['edit'] && $values['k_id']) {
            if ($values['k_id'] && $this->user->isAllowed('admin/kola', 'edit')) {
                $this->kolaRepository->update((int) $values['k_id'], $data);
                $this->flashMessage('Kolo bylo úspěšně zaktualizováno!', 'info');
                $this->redirect(':Admin:Kola:Detail', ['id' => (int) $values['k_id']]);
            }else{
                $this->flashMessage('Nemáte dostatečná oprávnění pro úpravu kol.', 'error');
            }
        }else{
            if (!$values['k_id'] && $this->user->isAllowed('admin/kola', 'add')) {
                $this->kolaRepository->add($data);
                $this->flashMessage('Kolo bylo úspěšně přidáno!', 'info');
            }else{
                $this->flashMessage('Nemáte dostatečná oprávnění pro přidání nových kol.', 'error');
            }
        }

        $this->redirect(':Admin:Kola:Default');
    }



}
