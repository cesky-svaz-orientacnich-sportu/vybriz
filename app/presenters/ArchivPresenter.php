<?php

namespace App\Presenters;

use Nette,
	App\Model;

/**
 * Archiv presenter.
 */
class ArchivPresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

    /** @persistent int */
    public $rok;

//    /** @persistent */
//    public $filtr = array();
//
//
    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }
//
//    public function getFiltrKola()
//    {
//    	if (isset($this->filtr['kola'])) {
//    		return $this->filtr['kola'];
//    	}
//
//    	return array();
//
//    }

	public function renderDefault($rok = 0)
	{
		$rok = ($rok >= 2016 && $rok <= date("Y", time()) + 2) ? (int) $rok : $this->database->table('kola')->select('rok')->order('rok DESC')->limit(1)->fetch()->rok;
		$kola = $this->database->table('kola')->select('id')->where('do < CURDATE() AND rok >= ?',$rok)->fetchPairs(NULL, 'id');

/*		$vr = $this->database->table('prihlasky')->where('stav ? AND kolo IN (?)', 'confirmed', $kola);

*/
		$allow_view = $this->user->isAllowed('aktualni-vr', 'view');

//		if($allow_view){
			$vr = $this->database->table('prihlasky')->where('stav ? AND kolo IN (?)', array('confirmed','submitted'), $kola);
//		}

//		if (count($this->filtrKola)>0) {
//			$vr->where('kolo',$this->filtrKola);
//		}
//		
//				


		$this->template->allow_view = $allow_view;
		$this->template->rok = $rok;
		$this->template->vr = $vr->order('termin.termin ASC');
    	$this->template->kolo = $this->database->table('kola')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();
		

		/**
		 * Náhrada za $vr[..]->ref() tabulky v šabloně
		 */
		$vr_clone = clone $vr;
		$ids = $vr_clone->fetchPairs(NULL, 'id');

		$ref_kola = $this->database->table('kola')->where(':prihlasky.id IN (?)',$ids);
		$ref_terminy = $this->database->table('terminy')->where(':prihlasky.id IN (?)',$ids);
		$ref_druhy = $this->database->table('druhy')->where(':prihlasky.id IN (?)',$ids);


		$this->template->ref_kola = $ref_kola->fetchPairs('id',NULL);
		$this->template->ref_terminy = $ref_terminy->fetchPairs('id',null);
		$this->template->ref_druhy = $ref_druhy->fetchPairs('id',null);

		$this->template->all_years = $this->database->table('kola')->select('rok')->order('rok ASC')->group('rok');
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

		$stav = array('confirmed','submitted');
		$prihlaska = $this->database->table('prihlasky');

		if ($this->user->isInRole('supervisor')) {
			$prihlaska->wherePrimary($id);
		}else{
			$prihlaska->where('prihlasky.id ? AND stav ? AND kolo.do < CURDATE()', $id, $stav);
		}

		$prihlaska = $prihlaska->limit(1)->fetch();

		if(!$prihlaska){
			$this->flashMessage('Nelze zobrazit danou přihlášku.','error');
			$this->redirect('AktualniVr:');
		}

		$k = $this->database->table('kola')->get($prihlaska->kolo);
		if($k->do->getTimestamp() > time() && !$this->user->isInRole('supervisor')){
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
	}


}
