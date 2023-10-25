<?php

namespace App\Presenters;

use Nette;
use	App\Model;

class AktualniVrPresenter extends BasePresenter
{

    /** @var Nette\Database\Explorer */
    private $database;

    public function __construct(Nette\Database\Explorer $database)
    {
        $this->database = $database;
    }

	public function renderDefault()
	{
		$rok = $this->database->table('kola')->select('rok')->order('rok DESC')->limit(1)->fetch()->rok;
		$kola = $this->database->table('kola')->select('id')->where('do < CURDATE() AND rok = ?', $rok)->order('id DESC')->fetchPairs(NULL, 'id');
		$allow_view = $this->user->isAllowed('aktualni-vr', 'view');
		$vr = $kola ? $this->database->table('prihlasky')->where('stav ? AND kolo IN (?)', array('confirmed','submitted'), $kola[0]) : NULL;

		$this->template->allow_view = $allow_view;
		$this->template->rok = $rok;
		$this->template->vr = $vr ? $vr->order('termin.termin ASC') : NULL;
    	$this->template->kolo = $this->database->table('kola')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();
    	$this->template->loadMapsAPI = true;
	}

	public function actionDetail($id)
	{
		if(!$this->user->isAllowed('aktualni-vr', 'view')){
			$this->setView('nahled');
		}
	}

	public function renderDetail($id)
	{
		$allow_view = $this->user->isAllowed('aktualni-vr', 'view');

		if(!$allow_view){
			$this->flashMessage('Detail přihlášky může být zobrazen pouze po přihlášení uživatele.','error');
			$this->redirect('Sign:In');
		}

		if($allow_view){
			$stav = array('confirmed','submitted');
		}else{
			$stav = 'confirmed';
		}

		$prihlaska = $this->database->table('prihlasky')->where('prihlasky.id ? AND stav ? AND kolo.do < CURDATE()', $id, $stav)->limit(1)->fetch();

		if(!$prihlaska){
			$this->flashMessage('Nelze zobrazit danou přihlášku.','error');
			$this->redirect('AktualniVr:');
		}

		$k = $this->database->table('kola')->get($prihlaska->kolo);
		if($k->do->getTimestamp() > time()){
			$this->flashMessage('Přihláška byla podána v kole, které ještě neskončilo.','error');
			$this->redirect('AktualniVr:');
		}

		#$this->template->allow_view = $allow_view;
		$this->template->prihlaska = $prihlaska;

		$this->template->dalsi_stavitele = Nette\Utils\Json::decode($prihlaska['dalsi_stavitele']);
		$this->template->prostor_zavodu_mapa = Nette\Utils\Json::decode($prihlaska['prostor_zavodu_mapa']);
		$this->template->centrum_zavodu_mapa = Nette\Utils\Json::decode($prihlaska['centrum_zavodu_mapa']);
		$this->template->probehle_zavody = Nette\Utils\Json::decode($prihlaska['probehle_zavody']);
		$this->template->mapy_pokryvajici_prostor = Nette\Utils\Json::decode($prihlaska['mapy_pokryvajici_prostor']);

    	if ($prihlaska->termin && $prihlaska->druh_zavodu) {
    		$this->template->termin	= $termin	= $this->database->table('terminy')->get($prihlaska->termin);
    		$this->template->druh	= $druh 	= $this->database->table('druhy')->get($prihlaska->druh_zavodu);
    	}

    	$sdruzeny_termin = $this->database->table('terminy')->select('druh_id AS druh')->where('id ? OR souvisejici_termin ?', $termin->souvisejici_termin, $termin->id)->fetch();

    	$rel_druhy = $sdruzeny_termin ? array($sdruzeny_termin['druh'], $prihlaska->druh_zavodu) : $prihlaska->druh_zavodu;

		$this->template->soubory = $this->database->table('soubory')->where('prihlaska_id', $id)->fetchAll();
		$this->template->relevant = $this->database->table('prihlasky')->select('prihlasky.id AS id, druh_zavodu.druh_zkratka AS druh, prihlasky.preference AS preference, prihlasky.prostor_zavodu AS prostor, termin.termin AS termin')->where('prihlasky.poradatel_zkratka ? AND prihlasky.stav ? AND kolo.do < CURDATE()', $prihlaska->poradatel_zkratka, $stav)->order('termin.termin DESC');
		$this->template->relevant2 = $this->database->table('prihlasky')->select('prihlasky.id AS id, druh_zavodu.druh_zkratka AS druh, prihlasky.poradatel_zkratka AS poradatel, prihlasky.prostor_zavodu AS prostor, termin.termin AS termin')->where('termin.druh_id ? AND prihlasky.stav ? AND kolo.do < CURDATE()', $rel_druhy , $stav)->order('termin.termin ASC');
		$this->template->loadMapsAPI = true;
	}


	public function renderNahled($id)
	{
		$prihlaska = $this->database->table('prihlasky')->where('prihlasky.id ? AND stav ? AND kolo.do < CURDATE()', $id, array('confirmed','submitted'))->limit(1)->fetch();

		if(!$prihlaska){
			$this->flashMessage('Nelze zobrazit danou přihlášku.','error');
			$this->redirect('AktualniVr:');
		}

		$k = $this->database->table('kola')->get($prihlaska->kolo);
		if($k->do->getTimestamp() > time()){
			$this->flashMessage('Přihláška byla podána v kole, které ještě neskončilo.','error');
			$this->redirect('AktualniVr:');
		}

		$this->template->prihlaska = $prihlaska;
		$this->template->prostor_zavodu_mapa = Nette\Utils\Json::decode($prihlaska['prostor_zavodu_mapa']);
		$this->template->centrum_zavodu_mapa = Nette\Utils\Json::decode($prihlaska['centrum_zavodu_mapa']);

    	if ($prihlaska->termin && $prihlaska->druh_zavodu) {
    		$this->template->termin	= $termin	= $this->database->table('terminy')->get($prihlaska->termin);
    		$this->template->druh	= $druh 	= $this->database->table('druhy')->get($prihlaska->druh_zavodu);
    	}

    	$this->template->loadMapsAPI = true;
	}


	public function renderVypis($rok, $kolo_id)
	{
		$allow_view = $this->user->isAllowed('aktualni-vr', 'view');
		if(!$allow_view){
			$this->flashMessage('Výpis lze zobrazit pouze po přihlášení.','error');
			$this->redirect('Sign:In');
		}

		$kolo = $this->database->table('kola')->select('id, do, kolo')->where('id ? AND rok ?',$kolo_id,$rok)->limit(1)->fetch();
		$kolo_id = $kolo ? $kolo->id : 0;
		$vr = $this->database->table('prihlasky')->where('stav ? AND kolo IN (?)', array('confirmed','submitted'), $kolo_id);

		$konec_kola = strtotime($kolo->do);
		if ($konec_kola < (time()-60*60*25) && !$this->user->isInRole('supervisor')) {
			$this->flashMessage('Výpis lze zobrazit až po skončení kola, '.date('j. n. Y',$konec_kola).'.','error');
			$this->forward('AktualniVr:');
		}

		$terminy_table = $this->database->table('terminy')->where('kolo_id', $kolo_id)->fetchPairs('id', NULL);
		$druhy = $this->database->table('druhy')->fetchPairs('id','druh_zkratka');



		$prihlasky = array();
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
		//$term_arr = $this->database->table('terminy')->select('id AS termin_id, souvisejici_termin AS souvisejici_termin_id')->order('termin')->group('termin_id')->where('kolo_id',$kolo_id)->fetchPairs('termin_id','souvisejici_termin_id');


		$terminy = array();
		$exclude_term = array();

		foreach ($term_arr as $t_id => $s_id) {

			if(!in_array($t_id, $exclude_term) && $t_id != ''){

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


}
