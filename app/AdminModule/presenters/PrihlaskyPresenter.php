<?php

namespace App\AdminModule\Presenters;

use Nette,
    Nette\Application\UI\Form,
    Nette\Application\UI\Multiplier,
    Nette\Utils,
    App\Components,
	App\Model;

/**
 * Prihlasky presenter.
 */
class PrihlaskyPresenter extends BaseAdminPresenter
{

    /** @var int */
    public $kolo_id = 0;

    /** @var Nette\Database\Explorer */
    private $database;

    /** @var Model\PrihlaskyRepository */
    private $prihlaskyRepository;

    /** @var Model\KolaRepository */
    private $kolaRepository;

    /** @var Model\Caching\Storage */
    private $storage;

    public function __construct(
        Nette\Database\Explorer $database,
        Model\PrihlaskyRepository $prihlaskyRepository,
        Model\KolaRepository $kolaRepository,
        Nette\Caching\Storage $storage
    ) {
        $this->database = $database;
        $this->prihlaskyRepository = $prihlaskyRepository;
        $this->kolaRepository = $kolaRepository;
        $this->storage = $storage;
    }

    public function renderEdit($id)
    {
    	$this->template->loadMapsAPI = true;
    }

    /**
     * Úprava přihlášky v administraci
     *
     * need refactoring!!
     *
     * @param  [type] $id    [description]
     * @return [type]         [description]
     */
    public function actionEdit($id)
    {
        //Získáme přihlášku z Databáze
        $prihlaska = $this->database->table('prihlasky')->wherePrimary($id);

        //
        // Pokud nemám právo na úpravu přihlášky podle ACL
        //
        if (!$this->user->isAllowed('admin/prihlasky', 'edit')) {
            $this->flashMessage('Nemáte oprávnění pro úpravu přihlášek.','error');
            $this->forward(':Admin:Dashboard:Default');
        }


        //
        // Pokud nemám dostatečné pravomoci,
        // jsem omezen na úpravy pouze do konce kola
        //
        if(!$this->user->isInRole('supervisor'))
        {
            $prihlaska ->where('kolo.do >= CURDATE()');
        }

        //vrátím přihlášku
        $prihlaska = $prihlaska->fetch();

        //...a nebo taky ne - v tom případě přesměrujeme :)
        if(!$prihlaska){
            $this->flashMessage('Omlouváme se, ale daná přihláška neexistuje, nebo již skončil termín kola, kdy bylo možné provádět úpravy.','error');
            $this->forward(':Admin:Dashboard:Default');
        }


        $db_terminy = $this->database->table('terminy')->select('terminy.id, DATE_FORMAT(terminy.termin, ?) AS termin','%d. %m. %Y')
            ->where('kolo_id', $prihlaska->kolo)->fetchPairs('id','termin');

        $db_druhy = $this->database->table('terminy')->select('terminy.druh_id, druh_id.druh')
            ->where('kolo_id', $prihlaska->kolo)->fetchPairs('druh_id','druh');


        $this['editAppControl']->setPrihlaska($prihlaska);

        $this['editAppControl']['editAppForm']['druh']->setItems($db_druhy);
        $this['editAppControl']['editAppForm']['termin']->setItems($db_terminy);


        $this['editAppControl']['editAppForm']->setDefaults([
                'poradatel'                             => $prihlaska->poradatel,
                'poradatel_zkratka'                     => $prihlaska->poradatel_zkratka,
                'prostor_zavodu'                        => $prihlaska->prostor_zavodu,
                'popis_terenu'                          => $prihlaska->popis_terenu,
                'reditel_zavodu'                        => $prihlaska->reditel_zavodu,
                'hlavni_rozhodci_registracni_cislo'     => $prihlaska->hlavni_rozhodci_registracni_cislo,
                'hlavni_rozhodci'                       => $prihlaska->hlavni_rozhodci,
                'hlavni_rozhodci_trida'                 => $prihlaska->hlavni_rozhodci_trida,
                'stavitel_trati_registracni_cislo'      => $prihlaska->stavitel_trati_registracni_cislo,
                'stavitel_trati'                        => $prihlaska->stavitel_trati,
                'stavitel_trati_trida'                  => $prihlaska->stavitel_trati_trida,
                'web'                                   => $prihlaska->web,
                'km_lesa'                               => $prihlaska->km_lesa,
                'km_celkem'                             => $prihlaska->km_celkem,
                'km_nezmapovaneho_lesa'                 => $prihlaska->km_nezmapovaneho_lesa,
                'odpovedny_zpracovatel_mapy'            => $prihlaska->odpovedny_zpracovatel_mapy,
                'vlastnici_pozemku_zavod'               => $prihlaska->vlastnici_pozemku_zavod,
                'vlastnici_pozemku_shromazdiste'        => $prihlaska->vlastnici_pozemku_shromazdiste,
                'vlastnici_pozemku_parkovani'           => $prihlaska->vlastnici_pozemku_parkovani,
                'katastr_zavod'                         => $prihlaska->katastr_zavod,
                'organy_ochrany_lesa'                   => $prihlaska->organy_ochrany_lesa,
                'organy_ochrany_prirody'                => $prihlaska->organy_ochrany_prirody,
                'np'                                    => $prihlaska->np,
                'chko'                                  => $prihlaska->chko,
                'narodni_prirodni_rezervace'            => $prihlaska->narodni_prirodni_rezervace,
                'prirodni_rezervace'                    => $prihlaska->prirodni_rezervace,
                'narodni_prirodni_pamatka'              => $prihlaska->narodni_prirodni_pamatka,
                'prirodni_pamatka'                      => $prihlaska->prirodni_pamatka,
                'prirodni_park'                         => $prihlaska->prirodni_park,
                'natura2000_ptaci_oblast'               => $prihlaska->natura2000_ptaci_oblast,
                'natura2000_evropsky_vyznamna_lokalita' => $prihlaska->natura2000_evropsky_vyznamna_lokalita,
                'poznamky'                              => $prihlaska->poznamky
            ]+[
                'dalsi_poradatele'          => $this['editAppControl']->getPrihlaska()['dalsi_poradatele'],
                'mapy_pokryvajici_prostor'  => $this['editAppControl']->getPrihlaska()['mapy_pokryvajici_prostor'],
                'probehle_zavody'           => $this['editAppControl']->getPrihlaska()['probehle_zavody'],
                'dalsi_stavitele'           => $this['editAppControl']->getPrihlaska()['dalsi_stavitele']
            ]+[
                'druh'       => $prihlaska->druh->id,
                'termin'     => $prihlaska->termin,
                'preference' => $prihlaska->preference
            ]);


    }

    protected function createComponentEditAppControl()
    {
        $control = new Components\EditAppControl($this->database);
        return $control;
    }

    public function renderVypis($rok, $kolo_id)
    {
        $allow_view = $this->user->isAllowed('aktualni-vr', 'view');
        if(!$allow_view){
            $this->flashMessage('Výpis lze zobrazit pouze po přihlášení.','error');
            $this->redirect('Sign:In');
        }

        $kolo = $this->database->table('kola')->select('id, do, kolo')->where('id ? AND rok ?',$kolo_id,$rok)->limit(1)->fetch();
        if ($kolo['do'] >= Nette\Utils\DateTime::from('now') && !$this->user->isAllowed('admin/prihlasky', 'edit')) {
            $this->flashMessage('Výpis přihlášek bude možné zobrazit až po skončení kola.');
            $this->redirect(':Admin:Dashboard:default');
        }
        $kolo_id = $kolo ? $kolo->id : 0;
        $vr = $this->database->table('prihlasky')->select('*')->where('stav ? AND kolo IN (?)', array('confirmed','submitted'), $kolo_id);

        if (!$kolo) {
            echo "Nelze zobrazit danou přihlášku.";
            $this->terminate();
        }

        $konec_kola = strtotime($kolo->do);


        if ($konec_kola > (time()-60*60*25) && !$this->user->isInRole('supervisor')) {
            $this->flashMessage('Výpis lze zobrazit až po skončení kola, '.date('j. n. Y',$konec_kola).'.','error');
            $this->forward(':Admin:Kola:Detail', ['id' => $kolo_id]);
        }

        $terminy_table = $this->database->table('terminy')->where('kolo_id', $kolo_id)->fetchPairs('id', NULL);
        $druhy = $this->database->table('druhy')->fetchPairs('id','druh_zkratka');

        $prihlasky = [];
        foreach ($vr as $key => $prihlaska) {
            $prihlasky[$prihlaska->termin][$prihlaska->poradatel_zkratka] = $prihlaska;
        }

        /** Termíny přihlášek podaných v daném kole */
        $term_arr = $this->database->table('prihlasky')
            ->select('termin.termin, termin.id AS termin_id, termin.souvisejici_termin AS souvisejici_termin_id')
            ->where('kolo ? AND stav IN (?)', $kolo_id, array('confirmed','submitted'))
            ->order('termin.termin')->group('termin.id')
            ->fetchPairs('termin_id','souvisejici_termin_id');

        /** Termíny pro všechny data kola */
        $terminy = [];
        $exclude_term = [];
        foreach ($term_arr as $t_id => $s_id) {
            if (!in_array($t_id, $exclude_term) && $t_id != '') {
                $terminy[$t_id] = $s_id;
                $exclude_term[] = $s_id;
            }
        }

        $this->template->terminy_table = $terminy_table;
        $this->template->druhy = $druhy;
        $this->template->terminy = $terminy;
        $this->template->prihlasky = $prihlasky;
        $this->template->allow_view = $allow_view;
        $this->template->rok = $rok ? $rok : 'N/A';
        $this->template->kolo_id = $kolo_id ? $kolo_id : 0;
        $this->template->kolo = $kolo ? $kolo->kolo : 'N/A';
        $this->template->vr = $vr->order('termin.termin ASC');
    }


    public function renderOris($id)
    {
        $prihlaska = $this->prihlaskyRepository->getOne($id);
        $this['orisIdForm']->setDefaults([
                'p_id' => (int) $id,
                'oris_id' => $prihlaska['oris_id'],
                'kolo_id' => $prihlaska['kolo'],
            ]);
        $this->template->prihlaska = $prihlaska;
    }


    protected function createComponentOrisIdForm()
    {
        $form = new Form;
        $form->addHidden('p_id');
        $form->addHidden('kolo_id');
        $form->addText('oris_id', 'OrisId:');

        $form->addSubmit('send', 'Nastavit OrisId');
        $form->addProtection('Vypršela platnost formuláře.');

        // call method recoveryFormSucceeded() on success
        $form->onSuccess[] = [$this, 'orisIdFormSucceeded'];
        return $form;

    }

    public function orisIdFormSucceeded($form, $values)
    {
        if (!$this->user->isAllowed('admin/oris', 'edit')) {
            $this->flashMessage('Nemáte dostatečná práva pro úpravu OrisId.', 'error');
            $this->redirect('this');
        }

        $this->prihlaskyRepository->updateOrisId($values['p_id'], $values['oris_id']);
        $this->storage->clean(array(
            Nette\Caching\Cache::TAGS => ['prihlasky/' . (int) $values['kolo_id']],
        ));
        $this->flashMessage('OrisId bylo úspěšně nastaveno');
        $this->redirect('this');
    }




    protected function createComponentMultiOrisIdForm()
    {
        $callback = [$this, 'multiOrisIdFormSuccess'];

        $control = new Multiplier(function ($pId) use ($callback) {
            $form = new Form;

            $form->addText('oris_id', 'OrisId')
                ->setType('number')
                ->addCondition(Form::FILLED, FALSE)
                    ->addRule(Form::INTEGER);

            $form->addHidden('p_id', (int) $pId);

            $form->addSubmit('send', 'Uložit');
            $form->onSuccess[] = $callback;

            return $form;
        });
        return $control;
    }

    public function multiOrisIdFormSuccess($form, $values)
    {
        if (!$this->user->isAllowed('admin/oris', 'edit')) {
            $this->flashMessage('Nemáte dostatečná práva pro úpravu OrisId.', 'error');
            $this->redirect('this');
        }

        $this->prihlaskyRepository->updateOrisId($values['p_id'], $values['oris_id']);
        $kolo_id = $this->getParameter('id') ? $this->getParameter('id') : $this->getParameter('kolo_id');
        $this->storage->clean(array(
            Nette\Caching\Cache::TAGS => ['prihlasky/' . (int) $kolo_id],
        ));

        if ($this->isAjax()) {
            $this->payload->message = 'Success';
            $this->redrawControl('orisprihlasky');
        }else{
            $this->flashMessage('OrisId bylo úspěšně nastaveno');
            $this->redirect('this');
        }
    }


    public function renderMultiOris($id)
    {
        $this->template->kolo = $this->kolaRepository->getOne($id);
        $prihlasky = $this->prihlaskyRepository->getAllForRound($id);
        $this->template->prihlasky = $prihlasky;
    }

    public function actionAutoOris($rok = 0, $kolo_id = 0)
    {
        $date_from = $rok.'-01-01'; //1. ledna daného roku
        $date_to = $rok.'-12-31'; //31. prosince daného roku
        $region = 'ČR';
        $format = 'json';
        $method = 'getEventList';
        $oris_link = sprintf(
            'https://oris.orientacnisporty.cz/API/?format=%s&method=%s&datefrom=%s&dateto=%s&sport=0&all=0&reg=%s',
            $format,
            $method,
            $date_from,
            $date_to,
            $region
        );

        //Uložení do cache
        $cache = new Nette\Caching\Cache($this->storage);
        $formated_data = $cache->save('OrisLoaderFormated_y'.$rok.$kolo_id, function() use ($oris_link) {
                $raw_data = Utils\Json::decode(file_get_contents($oris_link), 1);
                $data = [];

                foreach ($raw_data['Data'] as $d) {
                    $data[$d['Date']][$d['Org1']['Abbr']] = $d;
                }

                return $data;
        }, [Nette\Caching\Cache::EXPIRE => '20 minutes']);

        $data2 = $this->prihlaskyRepository->getAllForYear($rok)->where('stav', 'submitted')->order('termin.termin ASC');

        if ($kolo_id) {
            $data2->where('kolo.id', $kolo_id);
        }


        $this->template->data1 = $formated_data;
        $this->template->data2 = $data2;
        $this->template->kolo = $this->kolaRepository->getOne($kolo_id);
    }



    public function renderDefault($rok = 0, $kolo_id = 0)
    {
        $cache_name = 'PrihlaskyFinder_y'.$rok."_".$kolo_id;
        $cache = new Nette\Caching\Cache($this->storage);
        $prihlasky = $cache->call([$this, 'loadFinderData'], $rok, $kolo_id, [Nette\Caching\Cache::TAGS => 'prihlasky/' . (int) $kolo_id]);

        $this->template->prihlasky = $prihlasky;
    }

    /**
     * Načte data z databáze a vrátí JSON data
     * @param  integer $rok     - rok, na který se přihlášky podávají
     * @param  integer $kolo_id - ID kola
     * @return string
     */
    public function loadFinderData($rok = 0, $kolo_id = 0)
    {
        $data = [];
        $prihlasky = $this->prihlaskyRepository->getAll();

        if ($rok) {
            $prihlasky->where('rok', (int) $rok);
        }

        if ($kolo_id) {
            $prihlasky->where('kolo.kolo', (int) $kolo_id);
        }

        foreach ($prihlasky as $key => $p) {
            $data[] = [
                $p['id'],
                $p['druh'],
                $p['sdruzena_prihlaska_id'],
                $p['poradatel_zkratka'],
                $p['stav'],
                $p['created_at']->format("Y-m-d H:i:s"),
                $p['kolo'],
                $p['oris_id'],
            ];
        }

        return Utils\Json::encode($data, 1);
    }


    public function handleUpdateCoords()
    {
        if ($this->isAjax()) {
            $pId = $this->getParameter('id');

            $cz = $this->getParameter('centrum_zavodu');
            $pz = $this->getParameter('prostor_zavodu');
            $centrum_zavodu = Utils\Json::encode($cz,1);
            $prostor_zavodu = Utils\Json::encode($pz,1);

            $up = $this->database->table('prihlasky')->get($pId)->update(array(
                    'prostor_zavodu_mapa' => $prostor_zavodu,
                    'centrum_zavodu_mapa' => $centrum_zavodu
                ));

            $resp = array('ok'=>$up,'centrum_zavodu'=>$centrum_zavodu,'prostor_zavodu'=>$prostor_zavodu);
            $this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));

        }
    }


}
