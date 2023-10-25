<?php

namespace App\Presenters;

use Nette,
	Nette\Utils,
	App\Model,
	App\Components;

/**
 * Prihlaska presenter.
 */
class PrihlaskaPresenter extends BasePresenter
{
	private $hash_salt = "juot4QHLCABM6ZWBOUDElqZ6vNlRfVB3";

	/** @var Nette\Database\Explorer */
	private $database;

	/** @persistent */
	public $krok;

	/** @persistent */
	public $pId;

	/** @persistent */
	public $hash;

	/** @var Model\SessionControler */
	private $sessionControler;


    /** @var Nette\Mail\Mailer @inject */
    public $mailer;


    /** @var Model\AccessControler @inject */
    public $accessControler;


	public function __construct(Nette\Database\Explorer $database, Model\SessionControler $sessionControler)
	{
		$this->database = $database;
		$this->sessionControler = $sessionControler;
	}

	public function renderDefault()
	{
		$kolo = $this->database->table('kola')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();
		$this->template->kolo = $kolo;
		$this->template->aktivni_prihlasky = $this->sessionControler->prihlaskyByState('draft');


		$this->template->last_application = $last_application = $this->sessionControler->getLastApplication();

		if (!is_null($last_application)){
			$this->template->prihlaska	 = $prihlaska 	= $this->database->table('prihlasky')->select('termin, druh_zavodu, sdruzit_prihlasku, sdruzena_prihlaska_id, stav')->wherePrimary($last_application[0]['id'])->limit(1)->fetch();

			$sdruzena_prihlaska = $this->database->table('prihlasky')->select('stav')->wherePrimary($prihlaska->sdruzena_prihlaska_id)->fetch();
			if($sdruzena_prihlaska){
				$allow_duplication = $sdruzena_prihlaska->stav == 'submitted' ? TRUE : FALSE;
			}else{
				$allow_duplication = TRUE;
			}

			$this->template->allow_duplication = $allow_duplication;

			$this->template->termin		 = $termin 		= $this->database->table('terminy')->get($prihlaska->termin);
			$this->template->druh		 = $druh 		= $this->database->table('druhy')->get($prihlaska->druh_zavodu);
		}

		$this->template->accessRequest = $this->accessControler->getAccessData();
		if ($this->template->accessRequest && $this->template->accessRequest['mail'] != "") {
			$this->template->all_applications = $this->database->table('prihlasky')
				->where('mail', $this->template->accessRequest['mail'])
				->order('kolo DESC, created_at DESC');
		}
	}


	public function renderOdeslana()
	{
		$last_application = $this->sessionControler->getLastApplication();

		if(!$last_application){
			$this->flashMessage('Na aktuální relaci není dostupná žádná předchozí přihláška.','error');
			$this->redirect('Prihlaska:');
		}

		$this->template->last_application = $last_application;
	}


	public function actionNova()
	{
		if($this->pId && $this->hash){
			$this->sessionControler->setId($this->pId);

			//nastavení nejvyššího kroku
			$this->sessionControler->increaseStep($this->krok);

			$verification = $this->sessionControler->verifyHash($this->hash);

			if(!$verification){
				$this->sessionControler->clearPrihlaskySection($this->pId);
				$this->pId = $this->hash = $this->krok = NULL;

				$this->flashMessage('Došlo k chybě při ověřování platnosti formuláře. Zkuste to prosím znovu.','error');
				$this->redirect('Prihlaska:');
			}
		}else{
			$aktivni_prihlasky = $this->sessionControler->prihlaskyByState('draft');
			if(count($aktivni_prihlasky)>=1){
				$this->pId && $this->hash = NULL;
				$this->flashMessage('Pro vytvoření nové žádosti je potřeba dokončit všechny aktivní přihlášky.', 'error');
				$this->redirect('Prihlaska:');
			}
		}


	}


	public function renderNova()
	{
		//Pokud není otevřeno žádné kolo
		$kolo = $this->database->table('kola')->where('od <= CURDATE() AND do >= CURDATE()')->limit(1)->count();
		if(!$kolo){
			//Vymažeme aktivní přihlášky
			$aktivni_prihlasky = $this->sessionControler->prihlaskyByState('draft');
			if(count($aktivni_prihlasky)>=1){ $this->sessionControler->clearPrihlaskySection(); }

			//Přesměrujeme
			$this->flashMessage('Nelze podat přihlášku do otevření kola.','info');
			$this->krok = $this->pId = $this->hash = NULL;
			$this->redirect('Prihlaska:');
		}

		if(!$this->hash)
			{
				$this->pId = $this->krok = NULL;
				//$this->hash = Utils\Random::generate(6,'0-9a-z');
				//$session = $this->getSession('prihlasky'); // = get section
				//$session->setExpiration(0, 'serverkey');

				$kolo = $this->database->table('kola')->where('od <= CURDATE() AND do >= CURDATE()')->limit(1)->count();
				if(!$kolo){
					$this->flashMessage('Nelze podat přihlášku do otevření kola.','info');
					$this->krok = $this->pId = $this->hash = NULL;
					$this->redirect('Prihlaska:');
				}

				//$session->serverkey = crypt($this->hash,"$2y$".$this->hash_salt);
				//$this->database->table('hash')->insert(array('hash'=>$this->hash,'expiration'=>time()+3600*5));
			}
		elseif($this->hash && !$this->verifyHash($this->hash)) {

			$this->flashMessage('Neplatný klíč','error');
			$this->krok = $this->pId = $this->hash = NULL;
			$this->redirect('Prihlaska:');
			//$this->database->table('hash')->where('hash',$this->hash)->update(array('hash'=>$this->hash,'expiration'=>time()+3600*5));
		}elseif($this->pId) {
			$check = $this->database->table('prihlasky')->get($this->pId);
			if($check->hash != $this->hash){
				$this->flashMessage('Daný klíč se neshoduje s klíčem v databázi','error');
				$this->krok = $this->pId = $this->hash = NULL;
				$this->redirect('Prihlaska:');
			}
		}elseif(!$this->pId && (!$this->krok || 0 < $this->krok) && !$this->hash) {
			$this->flashMessage('Chybně zadané ID','error');
			$this->krok = $this->pId = $this->hash = NULL;
			$this->redirect('Prihlaska:Nova');
		}

		//předání kroku v proměnné do šablony
		$this->template->krok = $this->krok;

		//nejvyšší krok
		$this->template->highest_step = $highest_step = $this->pId ? $this->sessionControler->highestStep() : 0;
		if($highest_step>2){
			$this['prihlaskaForm-krok2']->setDefaults(array('p1'=>TRUE,'p2'=>TRUE,'p3'=>TRUE));
		}

		if($this->pId)
		{
			$formValues = $this->database->table('prihlasky')->get($this->pId);

			if($formValues->stav === 'submitted'){
				//vymazání záznamu ze session
				$this->sessionControler->changeState('submitted');

				$this->pId = $this->krok = $this->hash = NULL;
				$this->flashMessage('Přihláška již byla odeslána ke zpracování', 'error');
				$this->redirect('Prihlaska:');
			}

			if($this->krok > 1 && (is_null($formValues->termin) || is_null($formValues->druh_zavodu))){
				$this->krok = 1;
				$this->sessionControler->setStep(1);
				$this->redirect('this');
			}

			if($this->krok == 0){
				$this['prihlaskaForm']['krok0']->setDefaults(array(
						'registracni_cislo' => $formValues->registracni_cislo,
						'jmeno' => $formValues->jmeno,
						'mail' => $formValues->mail,
						'pozice_v_oddile' => $formValues->pozice_v_oddile
					));
				$this->template->krok = $this->krok;
			}elseif($this->krok == 1){
				if($formValues->druh_zavodu){
					$kolo = $this->database->table('kola')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();
					$terminy = $this->database->table('terminy')->where('druh_id ? AND kolo_id ?',$formValues->druh_zavodu, $kolo->id)->fetchPairs('id', 'termin');


					foreach ($terminy as $termin_id => $termin){
						$terminy[$termin_id] = $this::dateNiceFormat(array($termin, NULL));
					}


					$this['prihlaskaForm']['krok1']['termin']->setItems($terminy);

					$this['prihlaskaForm']['krok1']->setDefaults(array(
							'sdruzeny_termin' => $formValues->sdruzit_prihlasku,
							'termin' => $formValues->termin,
							'preference' => $formValues->preference,
							'druh' => $formValues->druh_zavodu
						));
				}
			}elseif($this->krok == 2){
				$mapy_pokryvajici_prostor = $formValues->mapy_pokryvajici_prostor ? Utils\Json::decode($formValues->mapy_pokryvajici_prostor,1) : array();
				$probehle_zavody = $formValues->probehle_zavody ? Utils\Json::decode($formValues->probehle_zavody,1) : array();
				$dalsi_stavitele = $formValues->dalsi_stavitele ? Utils\Json::decode($formValues->dalsi_stavitele,1) : array();

				$dalsi_poradatele_db = explode(', ', $formValues->dalsi_poradatele);
				$dalsi_poradatele = array();
				foreach ($dalsi_poradatele_db as $dalsi_poradatel) {
					$dalsi_poradatele[] = array('oddil_zkratka' => $dalsi_poradatel);
				}

				$this['prihlaskaForm']['krok2']->setDefaults(array(
						'poradatel' 							=> $formValues->poradatel,
						'poradatel_zkratka' 					=> $formValues->poradatel_zkratka,
						'prostor_zavodu' 						=> $formValues->prostor_zavodu,
						'popis_terenu' 							=> $formValues->popis_terenu,
						'reditel_zavodu' 						=> $formValues->reditel_zavodu,
						'hlavni_rozhodci_registracni_cislo' 	=> $formValues->hlavni_rozhodci_registracni_cislo,
						'hlavni_rozhodci' 						=> $formValues->hlavni_rozhodci,
						'hlavni_rozhodci_trida' 				=> $formValues->hlavni_rozhodci_trida,
						'stavitel_trati_registracni_cislo' 		=> $formValues->stavitel_trati_registracni_cislo,
						'stavitel_trati' 						=> $formValues->stavitel_trati,
						'stavitel_trati_trida' 					=> $formValues->stavitel_trati_trida,
						'web' 									=> $formValues->web,
						'km_lesa' 								=> $formValues->km_lesa,
						'km_celkem' 							=> $formValues->km_celkem,
						'km_nezmapovaneho_lesa' 				=> $formValues->km_nezmapovaneho_lesa,
						'odpovedny_zpracovatel_mapy' 			=> $formValues->odpovedny_zpracovatel_mapy,
						'vlastnici_pozemku_zavod' 				=> $formValues->vlastnici_pozemku_zavod,
						'vlastnici_pozemku_shromazdiste' 		=> $formValues->vlastnici_pozemku_shromazdiste,
						'vlastnici_pozemku_parkovani' 			=> $formValues->vlastnici_pozemku_parkovani,
						'katastr_zavod' 						=> $formValues->katastr_zavod,
						'organy_ochrany_lesa' 					=> $formValues->organy_ochrany_lesa,
						'organy_ochrany_prirody' 				=> $formValues->organy_ochrany_prirody,
						'np' 									=> $formValues->np,
						'chko' 									=> $formValues->chko,
						'narodni_prirodni_rezervace' 			=> $formValues->narodni_prirodni_rezervace,
						'prirodni_rezervace' 					=> $formValues->prirodni_rezervace,
						'narodni_prirodni_pamatka' 				=> $formValues->narodni_prirodni_pamatka,
						'prirodni_pamatka' 						=> $formValues->prirodni_pamatka,
						'prirodni_park' 						=> $formValues->prirodni_park,
						'natura2000_ptaci_oblast' 				=> $formValues->natura2000_ptaci_oblast,
						'natura2000_evropsky_vyznamna_lokalita' => $formValues->natura2000_evropsky_vyznamna_lokalita,
						'poznamky' 								=> $formValues->poznamky
					)+array(
						'dalsi_poradatele' 			=> $dalsi_poradatele,
						'mapy_pokryvajici_prostor' 	=> $mapy_pokryvajici_prostor,
						'probehle_zavody' 			=> $probehle_zavody,
						'dalsi_stavitele' 			=> $dalsi_stavitele
					));

					$this->template->soubory = $this->database->table('soubory')->where('prihlaska_id',$this->pId);
					$this->template->centrum_zavodu_mapa = $formValues->centrum_zavodu_mapa ? Utils\Json::decode($formValues->centrum_zavodu_mapa,1) : NULL;
					$this->template->prostor_zavodu_mapa = $formValues->prostor_zavodu_mapa ? Utils\Json::decode($formValues->prostor_zavodu_mapa,1) : NULL;
			}elseif ($this->krok == 3) {
				$this->template->soubory = $this->database->table('soubory')->where('prihlaska_id',$this->pId);
				$this->template->terminy = $this->database->table('terminy')->fetchPairs('id');
			}

			$this->template->formValues = $formValues;

			if (2 <= $this->krok && $this->krok <= 3) {
				$this->template->termin	= $this->database->table('terminy')->get($formValues->termin);
				$this->template->druh	= $this->database->table('druhy')->get($formValues->druh_zavodu);
			}

			$this->template->loadMapsAPI = true;
		}

	}


	public function verifyHash($hash)
	{
		$this->sessionControler->setId($this->pId);
		$verification = $this->sessionControler->verifyHash($hash);

		return $verification;
	}

	static function dateNiceFormat($arr)
	{
		$ev_start = strtotime($arr[0]);
		$ev_end = strtotime($arr[1]);

		$cz_days = array(
				1 => 'Po',
				2 => 'Út',
				3 => 'St',
				4 => 'Čt',
				5 => 'Pá',
				6 => 'So',
				7 => 'Ne'
			);

		if($ev_start!=$ev_end && $ev_end != NULL){
			$return = $cz_days[date("N",$ev_start)].' '.date("j.n.",$ev_start).' - '.$cz_days[date("N",$ev_end)].' '.date("j.n.",$ev_end);
		} else {
			$return = $cz_days[date("N",$ev_start)].' '.date("j.n.",$ev_start);
		}

		return $return;
	}

	public static function stepValidator($item, $itemStep)
	{
		return $itemStep === $this->krok;
	}


	protected function createComponentPrihlaskaForm()
	{
		$html = new Utils\Html;
		$form = new Nette\Application\UI\Form;
		//$form->getElementPrototype()->class = 'ajax';


		$step = array();



		$step[0] = $form->addContainer('krok0');

		$step[0]->addText('registracni_cislo', 'Registrační číslo')
			->setAttribute('class', 'cols2')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired()
					->addRule(Nette\Forms\Form::PATTERN, 'Zadejte platné registrační číslo', '([a-zA-Z]){3}([0-9]){4}');

		$step[0]->addText('jmeno', 'Jméno')
			->setAttribute('class', 'cols2')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[0]->addText('mail', 'E-mail')
			->setAttribute('class', 'cols2')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[0]->addText('pozice_v_oddile', 'Pozice v klubu/oddíle')
			->setAttribute('class', 'cols2')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();










		$step[1] = $form->addContainer('krok1');

		$step[1]->addSelect('druh', 'Druh závodu')->setPrompt('Zvolte druh')
			->setAttribute('onchange','changeDates(this.value)')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[1]->addRadioList('termin', 'Termín závodu')
			->setAttribute('onchange','dateAssoc(this.value)');	//'required' nastaveno při zpracování dat formuláře

		$step[1]->addCheckbox('sdruzeny_termin', 'Po odeslání přihlášky vytvořit nový formulář pro sdružený termín');

		$step[1]->addSelect('preference', 'Preference závodu', array(
				'1' => 1,
				'2' => 2,
				'3' => 3,
				'4' => 4,
				'5' => 5,
				'6' => 6,
				'7' => 7
			))
			->setAttribute('class', 'cols1')
			->setPrompt('-')
			->setOption('description', 'Zadá pořadatel, pokud podává více přihlášek do výběrového řízení současně a o některý závod má větší zájem (menší číslo = větší preference).');


		if($this->krok == 1){
			//$druhy = $this->database->table('druhy')->having('count(terminy.id)>0')->fetchPairs('id','druh');
			$kolo = $this->database->table('kola')->where('od <= CURDATE() AND do >= CURDATE()')->limit(1)->fetch();
			$druhy = $this->database->table('terminy')->select('druh_id.id AS id, druh_id.druh AS druh')->where('kolo_id',$kolo->id)->fetchPairs('id','druh');
			$step[1]['druh']->setItems($druhy);
		}










		$step[2] = $form->addContainer('krok2');



		/////Pořadatel
		$step[2]->addText('poradatel', 'Pořadatel')
			->setAttribute('class', 'cols5')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('poradatel_zkratka', 'Zkratka')
			->setAttribute('class', 'cols1')
			->setAttribute('maxlength', '3')
				->addCondition(array($this, 'stepConditionValidator'))
				->addRule($form::LENGTH, 'Zkratka oddílu musí mít %d znaky', 3)
				->setRequired();




		/////Další pořadatelé
		$dalsi_poradatele = $step[2]->addDynamic('dalsi_poradatele', function (Nette\Forms\Container $dp) {

			$dp->addText('oddil_zkratka', 'Zkratka oddílu')
				->setAttribute('class', 'cols1')
				->setAttribute('maxlength', '3');


			$dp->addSubmit('remove', '×')
				->setValidationScope([]) # disables validation
				->onClick[] = array($this, 'PrihlaskaFormRemoveElementClicked');

		}, 1)->addSubmit('add', 'Přidat položku')
			->setValidationScope([])
			->addCreateOnClick(TRUE);





		/////Informace o prostoru závodu
		$step[2]->addText('prostor_zavodu', 'Prostor závodu')
			->setAttribute('class', 'cols5')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addTextArea('popis_terenu', 'Popis terénu závodu')
			->setAttribute('class', 'texarea-wide')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('prostor_odkaz', 'Odkaz prostor')
			->setAttribute('class', 'cols5');



		/////Organizátoři
		$step[2]->addText('reditel_zavodu', 'Ředitel závodu')
			->setAttribute('class', 'cols2')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('hlavni_rozhodci_registracni_cislo', 'Hlavní rozhodčí - registrační číslo')
			->setAttribute('class', 'cols2')
			->setAttribute('maxlength', 7)
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('hlavni_rozhodci', 'Hlavní rozhodčí')
			->setAttribute('class', 'cols2 noborder')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('hlavni_rozhodci_trida', 'Třída rozhodčího')
			->setAttribute('class', 'cols1 noborder')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('stavitel_trati_registracni_cislo', 'Stavitel tratí - registrační číslo')
			->setAttribute('class', 'cols2')
			->setAttribute('maxlength', 7)
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('stavitel_trati', 'Hlavní stavitel tratí')
			->setAttribute('class', 'cols2 noborder')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();

		$step[2]->addText('stavitel_trati_trida', 'Třída rozhodčího')
			->setAttribute('class', 'cols1 noborder')
				->addCondition(array($this, 'stepConditionValidator'))
				->setRequired();




		/////Další stavitelé
		$dalsi_stavitele = $step[2]->addDynamic('dalsi_stavitele', function (Nette\Forms\Container $ds) {


			$ds->addText('stavitel_trati_registracni_cislo', 'Stavitel tratí - registrační číslo')
				->setAttribute('class', 'cols2')
				->setAttribute('maxlength', 7);

			$ds->addText('stavitel_trati', 'Stavitel tratí')
				->setAttribute('class', 'cols2 noborder');

			$ds->addText('stavitel_trati_trida', 'Třída rozhodčího')
				->setAttribute('class', 'cols1 noborder');


			$ds->addSubmit('remove', '×')
				->setValidationScope([]) # disables validation
				->onClick[] = array($this, 'PrihlaskaFormRemoveElementClicked');

		}, 2)->addSubmit('add', 'Přidat položku')
			->setValidationScope([])
			->addCreateOnClick(TRUE);



		/*** Web závodu **/
		$step[2]->addText('web', 'Odkaz na web závodu')
			->setAttribute('class', 'cols4')
			->setOption('description', 'Vyplní pořadatel v případě, že připravil pro VŘ i další prezentaci svého závodu.');



		/////Mapa a předchozí aktivity prostoru
		$step[2]->addText('km_lesa', $html::el()->setHtml('km<sup>2</sup> lesa') )
			->setAttribute('class', 'cols1')/*
				->addCondition(array($this, 'stepConditionValidator'))
				->addCondition($form::FILLED, TRUE)
				->addRule($form::FLOAT, 'Položka musí být číslo.')*/;

		$step[2]->addText('km_celkem', $html::el()->setHtml('celkem km<sup>2</sup>') )
			->setAttribute('class', 'cols1');

		$step[2]->addText('km_nezmapovaneho_lesa', $html::el()->setHtml('km<sup>2</sup> lesa dosud nemapovaného') )
			->setAttribute('class', 'cols1');

		$step[2]->addText('odpovedny_zpracovatel_mapy', 'Odpovědný zpracovatel mapy')
			->setAttribute('class', 'cols2');



		/////Mapy pokrývající prostor
		$mapy_pokryvajici_prostor = $step[2]->addDynamic('mapy_pokryvajici_prostor', function (Nette\Forms\Container $m) {
			$m->addText('mapa', 'Mapa')
			->setAttribute('class', 'cols2');
			$m->addText('oddil', 'Vydáno oddílem')
			->setAttribute('style', 'width:50px');
			$m->addText('rok', 'Rok vydání')
			->setAttribute('style', 'width:50px');
			$m->addText('meritko', 'Měřítko')
			->setAttribute('class', 'cols1');
			$m->addSelect('disciplina', 'Disciplína', array(''=>'-','OB'=>'OB','LOB'=>'LOB','MTBO'=>'MTBO'));
			$m->addText('odkaz', 'Odkaz na mapu')
			->setAttribute('class', 'cols4');


			$m->addSubmit('remove', '×')
				->setValidationScope([]) # disables validation
				->onClick[] = array($this, 'PrihlaskaFormRemoveElementClicked');
		}, 3)->addSubmit('add', 'Přidat položku')
			#->setAttribute('class', 'ajax')
			->setValidationScope([])
			->addCreateOnClick(TRUE);



		/////Výpis všech závodů konaných v uvažovaném prostoru (i jeho části) za posledních 6 let

		$probehle_zavody = $step[2]->addDynamic('probehle_zavody', function (Nette\Forms\Container $pz) {
			$pz->addText('datum', 'Datum/rok')
				->setAttribute('class', 'cols2');
			$pz->addText('typ_zavodu', 'Typ závodu')
				->setAttribute('class', 'cols5');


			$pz->addSubmit('remove', '×')
				->setValidationScope([]) # disables validation
				->onClick[] = array($this, 'PrihlaskaFormRemoveElementClicked');
		}, 3);

		$probehle_zavody->addSubmit('add', 'Přidat položku')
			->setValidationScope([])
			//->onClick[] = array($this, 'PrihlaskaFormAddElementClicked');
			->addCreateOnClick(TRUE);




		/////Vlastníci pozemků a orgány státní správy

		$step[2]->addTextArea('vlastnici_pozemku_zavod', 'Seznam vlastníků lesních a dalších pozemků, přes které povede závod')
			->setAttribute('class', 'texarea-wide-high');
		$step[2]->addTextArea('vlastnici_pozemku_shromazdiste', 'Seznam vlastníků pozemku určených pro shromaždiště')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('vlastnici_pozemku_parkovani', 'Seznam vlastníků pozemku určených pro parkování')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('katastr_zavod', 'Seznam katastrálních území, na kterých se závod bude konat')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('organy_ochrany_lesa', 'Seznam dotčených orgánů ochrany lesa dle zákona o lesích')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('organy_ochrany_prirody', 'Seznam dotčených orgánů ochrany přírody dle zákona o ochraně přírody a krajiny 114/1992')
			->setAttribute('class', 'texarea-wide');


		/////Informace, zda se uvažovaný prostor nachází v chráněných územích (pokud ano, specifikujte)

		$step[2]->addTextArea('np', 'Národní park')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('chko', 'CHKO')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('narodni_prirodni_rezervace', 'Národní přírodní rezervace')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('prirodni_rezervace', 'Přírodní rezervace')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('narodni_prirodni_pamatka', 'Národní přírodní památka')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('prirodni_pamatka', 'Přírodní památka')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('prirodni_park', 'Přírodní park')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('natura2000_ptaci_oblast', 'Ptačí oblast (dle Natura 2000)')
			->setAttribute('class', 'texarea-wide');
		$step[2]->addTextArea('natura2000_evropsky_vyznamna_lokalita', 'Evropsky významná lokalita (dle Natura 2000)')
			->setAttribute('class', 'texarea-wide');



		/////Prohlášení
		$step[2]->addCheckbox('p1', 'Uspořádání závodu v tomto terénu nebrání vážné majetkové a legislativní důvody (zónace CHKO, apod.)');
		$step[2]->addCheckbox('p2', 'Výše uvedené osoby (osoba odpovědná za zpracování mapy, ředitel závodu, hlavní rozhodčí a stavitel tratí) s převzetím úkolu souhlasí a jsou ochotny daný úkol realizovat');
		$step[2]->addCheckbox('p3', 'K prostoru závodu nemá žádný jiný subjekt (klub, oddíl) právo k využití pro pořádání závodů, pokud ano, tento subjekt s pořádáním uvažovaného závodu souhlasí.');


		/////Přílohy
		$step[2]->addTextArea('files_arr', 'Soubory');

		///Poznamky
		$step[2]->addTextArea('poznamky', 'Poznámky')
			->setAttribute('class', 'texarea-wide-high');





		$form->addSubmit('send','Pokračovat');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');
		$form->onSuccess[] = [$this, 'prihlaskaFormSubmitted'];
		$form->onValidate[] = array($this, 'validatePrihlaskaForm');

		return $form;
	}

	public function isEmpty($arr)
	{
		if($arr instanceof Utils\ArrayHash || is_array($arr)){
			$return = false;
			foreach ($arr as $key => $val) {
				if(!$this->isEmpty($val)){
					return false;
				}
			}
			return true;
		}
		return ($arr == "") ? true : false;
	}

	public function cleanArray($array)
	{
		$helpArr = array();
		foreach ($array as $innerArray) {
			if(!$this->isEmpty($innerArray)){
				$helpArr[] = $innerArray;
			}
		}
		return $helpArr;
	}

	/* Nastaví podmínku pro aktuální krok fomuláře, která dále umožňuje nastavení dalších valid. pravidel (required, ...) */
	public function stepConditionValidator($control)
	{
		$krok = $this->krok ? $this->krok : 0;
		return $control->parent->name == 'krok'.$krok ? TRUE : FALSE;
	}

	/* Validace daného kroku formuláře */
	public function validatePrihlaskaForm($form)
	{
		if($form['send']->isSubmittedBy()){
			$labels = array();
			$krok = $this->krok ? $this->krok : 0;

			foreach ($form['krok'.$krok]->controls as $control) {
				if(!$control->getRules()->validate()){
					$labels[] = Nette\Utils\Strings::lower($control->label->getText());
				}
			}


			if($labels){
				$form->addError("Následující pole nebyla správně vyplněna: ".implode(', ', $labels));
			}
		}


	}

	// volá se po úspěšném odeslání formuláře
	public function PrihlaskaFormSubmitted($form)
	{
		if ($form['send']->isSubmittedBy()) {
			$values = $form->getValues(TRUE);

			$step = $this->krok;
			$pId = $this->pId;
			$hash = $this->hash;

			if($step == 0){

				if(!$pId){
					//převede reg. č. na řetězec s velkými písmeny
					$values['krok0']['registracni_cislo'] = Utils\Strings::upper($values['krok0']['registracni_cislo']);

					//zkratka klubu
					$odd_abbr = substr($values['krok0']['registracni_cislo'],0,3);

					//vybere aktuální kolo
					$kolo = $this->database->table('kola')->select('id')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();

					//Nahraje data o oddíle z ORISu
					$json_data = Utils\Json::decode(file_get_contents('https://oris.orientacnisporty.cz/API/?format=json&method=getClub&id='.$odd_abbr), TRUE);
					$odd = ($json_data && @$json_data['Status'] === 'OK') ? $json_data['Data']['Name'] : '?';

					$prihlasky_table = $this->database->table('prihlasky');

					//vytvoří záznam přihlášky v databázi
					$inserted = $prihlasky_table->insert($values['krok0']+array('poradatel_zkratka' => $odd_abbr, 'kolo' => $kolo->id, 'poradatel' => $odd));

					//nastavení parametrů pro session ověřování
					$this->sessionControler->setId($inserted->id);
					$h = $this->sessionControler->newHash();

					//update přihlášek -> session hash
					$prihlasky_table->wherePrimary($inserted->id)->update(array('hash' => $h));
					$this->hash = $h;

					//nastaví persistent. parametr pId přihlášky podle id posledního vloženého záznamu
					$this->pId = $inserted->id;
				}else{
					$updated = $this->database->table('prihlasky')->get($pId)->update($values['krok0']);
				}

				$this->krok = 1;
			}elseif($step == 1){

				//získání dat, která nejsou ověřována oproti výchozím hodnotám prvku; = $form->getValues bez validace
				$termin_id = (int) $form->getHttpData($form::DATA_LINE, 'krok1[termin]');
				if(!$termin_id){
					$this->flashMessage('Vyberte termín závodu.', 'error');
					$this->redirect('this');
				}

				$termin = $this->database->table('terminy')->get($termin_id);
				if(!$termin){
					$this->flashMessage('Termín s tímto ID neexistuje!', 'error');
					$this->redirect('this');
				}

				$sdruzit = ($termin->souvisejici_termin && $values['krok1']['sdruzeny_termin']) ? 1 : 0;

				if($termin->druh_id != $values['krok1']['druh']){
					$this->flashMessage('Podstrčený termín nelze přiřadit k danému druhu závodu!', 'error');
					$this->redirect('this');
				}

				$updated = $this->database->table('prihlasky')->get($pId)->update(array(
						'druh_zavodu' => $values['krok1']['druh'],
						'termin' => $termin_id,
						'preference' => $values['krok1']['preference'],
						'sdruzit_prihlasku' => $sdruzit
					));

				$this->krok = 2;

			}elseif($step == 2){

				$array_to_clean = array(
						'mapy_pokryvajici_prostor' => $values['krok2']['mapy_pokryvajici_prostor'],
						'probehle_zavody' => $values['krok2']['probehle_zavody'],
						'dalsi_stavitele' => $values['krok2']['dalsi_stavitele']
					);

				foreach ($array_to_clean as $key => $arr) {
					$array_to_clean[$key] = $this->cleanArray($arr);
				}

				$mapy_pokryvajici_prostor = Utils\Json::encode($array_to_clean['mapy_pokryvajici_prostor'],1);
				$probehle_zavody = Utils\Json::encode($array_to_clean['probehle_zavody'],1);
				$dalsi_stavitele = Utils\Json::encode($array_to_clean['dalsi_stavitele'],1);

				$dalsi_poradatele = array();
				foreach ($values['krok2']['dalsi_poradatele'] as $dalsi_poradatel) {
					if ($dalsi_poradatel['oddil_zkratka'] != "") {
						$dalsi_poradatele[] = $dalsi_poradatel['oddil_zkratka'];
					}
				}
				$dalsi_poradatele = implode(', ', $dalsi_poradatele);

				$prihlaska = $this->database->table('prihlasky')->get($pId);
				$updated = $prihlaska->update(array(
						'poradatel' 							=> $values['krok2']['poradatel'],
						'poradatel_zkratka' 					=> $values['krok2']['poradatel_zkratka'],
						'prostor_zavodu' 						=> $values['krok2']['prostor_zavodu'],
						'popis_terenu' 							=> $values['krok2']['popis_terenu'],
						'reditel_zavodu' 						=> $values['krok2']['reditel_zavodu'],
						'hlavni_rozhodci_registracni_cislo' 	=> Utils\Strings::upper($values['krok2']['hlavni_rozhodci_registracni_cislo']),
						'hlavni_rozhodci' 						=> $values['krok2']['hlavni_rozhodci'],
						'hlavni_rozhodci_trida' 				=> $values['krok2']['hlavni_rozhodci_trida'],
						'stavitel_trati_registracni_cislo' 		=> Utils\Strings::upper($values['krok2']['stavitel_trati_registracni_cislo']),
						'stavitel_trati' 						=> $values['krok2']['stavitel_trati'],
						'stavitel_trati_trida' 					=> $values['krok2']['stavitel_trati_trida'],
						'web' 									=> $values['krok2']['web'],
						'km_lesa' 								=> $values['krok2']['km_lesa'],
						'km_celkem' 							=> $values['krok2']['km_celkem'],
						'km_nezmapovaneho_lesa' 				=> $values['krok2']['km_nezmapovaneho_lesa'],
						'odpovedny_zpracovatel_mapy' 			=> $values['krok2']['odpovedny_zpracovatel_mapy'],
						'vlastnici_pozemku_zavod' 				=> $values['krok2']['vlastnici_pozemku_zavod'],
						'vlastnici_pozemku_shromazdiste' 		=> $values['krok2']['vlastnici_pozemku_shromazdiste'],
						'vlastnici_pozemku_parkovani' 			=> $values['krok2']['vlastnici_pozemku_parkovani'],
						'katastr_zavod' 						=> $values['krok2']['katastr_zavod'],
						'organy_ochrany_lesa' 					=> $values['krok2']['organy_ochrany_lesa'],
						'organy_ochrany_prirody' 				=> $values['krok2']['organy_ochrany_prirody'],
						'np' 									=> $values['krok2']['np'],
						'chko' 									=> $values['krok2']['chko'],
						'narodni_prirodni_rezervace' 			=> $values['krok2']['narodni_prirodni_rezervace'],
						'prirodni_rezervace' 					=> $values['krok2']['prirodni_rezervace'],
						'narodni_prirodni_pamatka' 				=> $values['krok2']['narodni_prirodni_pamatka'],
						'prirodni_pamatka' 						=> $values['krok2']['prirodni_pamatka'],
						'prirodni_park' 						=> $values['krok2']['prirodni_park'],
						'natura2000_ptaci_oblast' 				=> $values['krok2']['natura2000_ptaci_oblast'],
						'natura2000_evropsky_vyznamna_lokalita' => $values['krok2']['natura2000_evropsky_vyznamna_lokalita'],
						'poznamky' 								=> $values['krok2']['poznamky']
					)+array(
						'mapy_pokryvajici_prostor' 	=> $mapy_pokryvajici_prostor,
						'probehle_zavody' 			=> $probehle_zavody,
						'dalsi_poradatele' 			=> $dalsi_poradatele,
						'dalsi_stavitele' 			=> $dalsi_stavitele
					));




				try {
					if(count(Utils\Json::decode($prihlaska['centrum_zavodu_mapa'])) == 0 || count(Utils\Json::decode($prihlaska['prostor_zavodu_mapa'])) == 0){
						$this->flashMessage('Zakreslete prosím centrum a prostor závodu do mapy.', 'error');
						$this->redirect('this');
					}
				} catch (Nette\Utils\JsonException $e) {
					$this->flashMessage('Zakreslete prosím centrum a prostor závodu do mapy.', 'error');
					$this->redirect('this');
				}


				//ověření, zda-li uživatel zaškrtnul políčka Prohlášení

				if(!$values['krok2']['p1'] || !$values['krok2']['p2'] || !$values['krok2']['p3']){
					$this->flashMessage('Pro pokračování musíte odsouhlasit prohlášení.', 'error');
					$this->redirect('this');
				}

				$this->krok = 3;
			}

			$this->redirect('this');
		}
	}





	public function PrihlaskaFormAddElementClicked(Nette\Forms\Controls\SubmitButton $button)
	{
		$form = $this['prihlaskaForm'];

		$users = $button->parent;

		// count how many containers were filled
		//if ($users->isAllFilled()) {
			// add one container to replicator
			$button->parent->createOne();
		//}
		$this->redirect('Prihlaska:Nova');
	}

	public function PrihlaskaFormRemoveElementClicked(Nette\Forms\Controls\SubmitButton $button)
	{
		// first parent is container
		// second parent is it's replicator
		$users = $button->parent->parent;
		$users->remove($button->parent, TRUE);

		$this['prihlaskaForm']->cleanErrors();
	}




	public function handleChangeDates($druh_id)
	{
		if ($this->isAjax()) {
			$kolo = $this->database->table('kola')->select('id')->where('do >= CURDATE()')->order('od ASC')->limit(1)->fetch();
			$terminy = $this->database->table('terminy')->select('id, termin')->where('druh_id ? AND kolo_id ?',$druh_id,$kolo->id)->order('termin ASC')->fetchPairs('id','termin');
			foreach ($terminy as $key => $termin) {
				$terminy[$key] = $this::dateNiceFormat(array($termin,NULL));
			}

			$tControl = $this['prihlaskaForm']['krok1']['termin'];
			$tControl->setItems($terminy);
			$html = $tControl->getControl()->getHtml();

			$resp = array('ok'=>'1', 'html_control'=>$html);
			$this->sendJson($resp); //same as $this->sendResponse(new Nette\Application\Responses\JsonResponse( ... ))
		}
	}



	public function handleDateAssoc($termin_id)
	{
		if ($this->isAjax()) {
			$termin = $this->database->table('terminy')->get($termin_id);
			$sdruzeny_termin = $termin->souvisejici_termin;

			if($sdruzeny_termin){
				$assoc = $this->database->table('terminy')->get($sdruzeny_termin);
				$druh = $this->database->table('druhy')->get($assoc->druh_id);
				$date = $this::dateNiceFormat(array($assoc->termin,NULL));
				$message = "Po odeslání přihlášky se automaticky vytvoří nový formulář pro termín <b>".$date."</b> (".$druh->druh.") s přednastavenými údaji z předchozího.";
			}else{
				$message = "Pro zvolený termín <i>".$this::dateNiceFormat(array($termin->termin,NULL))."</i> není přiřazen žádný sdružený termín.";
			}

			$enable_checkbox = $sdruzeny_termin ? TRUE : FALSE;


			$resp = array('enable_checkbox'=>$enable_checkbox, 'message'=>$message);
			$this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
		}
	}





	public function handleUpdateCoords()
	{
		if ($this->isAjax()) {
			$pId = $this->pId;

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




	public function handleUploadFile()
	{
		if($this->hash && $this->pId){
			if (empty($_FILES) || $_FILES['file']['error']) {
				$resp = array('OK'=>0,'info'=>'Failed to move uploaded file.');
				$this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
				$this->terminate();
			}

			$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
			$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

			$ds = DIRECTORY_SEPARATOR;
			$wwwDir = $this->getContext()->parameters['wwwDir'];

			$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
			$sanitized = trim(Utils\Strings::webalize($fileName, '.', FALSE), '.-');
			$filePath = $wwwDir.$ds.'uploads'.$ds.'tmp-'.$this->pId.'-'.$sanitized;


			// Open temp file
			$out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
			if ($out) {
				// Read binary input stream and append it to temp file
				$in = @fopen($_FILES['file']['tmp_name'], "rb");

				if ($in) {
					while ($buff = fread($in, 4096))
						fwrite($out, $buff);
				} else {
					$resp = array('OK'=>0,'info'=>'Failed to open input stream.','filepath'=> $filePath);
					$this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
					die();
				}

				@fclose($in);
				@fclose($out);

				@unlink($_FILES['file']['tmp_name']);
			} else {
				$resp = array('OK'=>0,'info'=>'Failed to open output stream.','filepath'=> $filePath);
				$this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
				die();

			}

			// Check if file has been uploaded
			if (!$chunks || $chunk == $chunks - 1) {
				$t = time().'-'.Utils\Random::generate(3,'A-Z');

				$upDir = $wwwDir.$ds.'files'.$ds;
				$files_count = 1000;
				$thousand = 0;
				while ($files_count >= 1000) {
					$thousand++;
					$dir = $upDir.(1000*($thousand-1)+1).'-'.(1000*$thousand);
					if( is_dir($dir) ){
						$files_count = count(scandir($dir))-2;
					}else{
						break;
					}
				}
				$folder_name = (1000*($thousand-1)+1).'-'.(1000*$thousand);


				$filePath2 = $wwwDir.$ds.'files'.$ds.$folder_name.$ds.$this->pId.'-'.$t.'-'.$sanitized;
				// Strip the temp .part suffix off
				Nette\Utils\FileSystem::rename("{$filePath}.part", $filePath2);


				$url = '/files/'.$folder_name.'/'.$this->pId.'-'.$t.'-'.$sanitized;


				$inserted = $this->database->table('soubory')->insert(array(
						'prihlaska_id' => $this->pId,
						'nazev' => $sanitized,
						'cesta' => $filePath2,
						'url' => $url
					));
				if(!$inserted){Nette\Utils\FileSystem::delete($filePath2);}
			}

			$resp = array('OK'=>1,'info'=>'File successfuly uploaded.','filepath'=> $filePath);
			$this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
			die();


		}else{
			$resp = array('OK'=>0,'info'=>'Error occured!');
			$this->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
			die();
		}
	}



	public function handleDeleteFile($file_id)
	{
		if($this->verifyHash($this->hash)){
			$file = $this->database->table('soubory')->get($file_id);

			if($file->prihlaska_id == $this->pId){

				//pokud nejde o kopii záznamu db (kopie != 1), smaže soubor z disku
				if($file->kopie === 0){
					Nette\Utils\FileSystem::delete($file->cesta);
				}


				$file->delete();
				$this->flashMessage('Soubor „'.$file->nazev.'“ byl úspěšně odstraněn.');
			}

			$this->redirect('this#section-files');
		}
	}

	public function handleUpdatelist()
	{
		$this->template->soubory = $this->database->table('soubory')->where('prihlaska_id',$this->pId);
		$this->redrawControl('files'); // invaliduje snippet 'header'
		$this->redrawControl('ajaxSnippetFormArea'); // invaliduje snippet 'header'
	}

	public function handleConfirmSubmit($pId, $hash)
	{
		if($this->verifyHash($hash)){
			$prihlaska = $this->database->table('prihlasky')->get($pId);
			$updated = $prihlaska->update(array('stav'=>'submitted'));

			//proběhl updated db v pořádku? (změna stavu na „odesláno“)
			if ($updated) {

				//směna stavu poslední přihlášky v SESSION
				$this->sessionControler->changeState('submitted');


				//zjištění dat pro sdružený termín
				$termin = $this->database->table('terminy')->get($prihlaska->termin);
				$sdruzeny_termin = $this->database->table('terminy')->get($termin->souvisejici_termin);
				$druh_zavodu = $this->database->table('druhy')->get($prihlaska->druh_zavodu);
				$kolo = $this->database->table('kola')->get($prihlaska->kolo);



				//odeslání mailu
				$template = $this->createTemplate();
				$template->setFile(__DIR__ . '/templates/components/prihlaska.mail.submitted.latte');
				$template->data = $prihlaska;
				$template->druh = $druh_zavodu;
				$template->termin = $termin;

				$mail = new Nette\Mail\Message;
				$mail->setFrom('Výběrové řízení OB <vybriz@orientacnisporty.cz>')
					->addTo($prihlaska->jmeno.' <'.$prihlaska->mail.'>')
					->setHtmlBody($template);


				$attachment = $this->createTemplate();
				$attachment->setFile(__DIR__ . '/templates/components/prihlaska.mail.attachment.latte');
				$attachment->prihlaska = $prihlaska;
				$attachment->druh = $druh_zavodu;
				$attachment->termin = $termin;
				$attachment->kolo = $kolo;
				$attachment->dalsi_stavitele = Nette\Utils\Json::decode($prihlaska['dalsi_stavitele']);
				$attachment->probehle_zavody = Nette\Utils\Json::decode($prihlaska['probehle_zavody']);
				$attachment->mapy_pokryvajici_prostor = Nette\Utils\Json::decode($prihlaska['mapy_pokryvajici_prostor']);
				$attachment->soubory = $this->database->table('soubory')->where('prihlaska_id', $pId)->fetchAll();

				$attachment_file_name = 'vybriz_zadost_'.$pId.'_'.Utils\Random::generate(4).'.html';

				$mail->addAttachment($attachment_file_name, $attachment, 'text/html');

				$file = Utils\FileSystem::write(  __DIR__ . '/../../mp/' . $attachment_file_name, $attachment );

				try {
					$this->mailer->send($mail);
				} catch (\Exception $e) {
					$this->flashMessage('Informační e-mail nemohl být odeslán, kontaktujte prosím spávce systému. (' . $e->getMessage() . ')', 'error');
				}



				//Vytvořit nový formulář?
				if($prihlaska->sdruzit_prihlasku){

					$predloha = is_null($prihlaska->predloha_sdruzene_prihlasky) ? $prihlaska : $this->database->table('prihlasky')->get($prihlaska->predloha_sdruzene_prihlasky);


					//tabulka přihlášek
					$prihlasky_table = $this->database->table('prihlasky');

					//vložení nové přihlášky do databáze
					$inserted = $prihlasky_table->insert(array(
							'stav' 									=> 'draft',
							'kolo' 									=> $predloha->kolo,
							'created_at' 							=> $predloha->created_at,
							'poradatel' 							=> $predloha->poradatel,
							'poradatel_zkratka' 					=> $predloha->poradatel_zkratka,
							'dalsi_poradatele' 						=> $predloha->dalsi_poradatele,
							'centrum_zavodu_mapa' 					=> $predloha->centrum_zavodu_mapa,
							'prostor_zavodu_mapa' 					=> $predloha->prostor_zavodu_mapa,
							'prostor_zavodu' 						=> $predloha->prostor_zavodu,
							'popis_terenu' 							=> $predloha->popis_terenu,
							'reditel_zavodu' 						=> $predloha->reditel_zavodu,
							'hlavni_rozhodci_registracni_cislo' 	=> $predloha->hlavni_rozhodci_registracni_cislo,
							'hlavni_rozhodci' 						=> $predloha->hlavni_rozhodci,
							'hlavni_rozhodci_trida' 				=> $predloha->hlavni_rozhodci_trida,
							'stavitel_trati_registracni_cislo' 		=> $predloha->stavitel_trati_registracni_cislo,
							'stavitel_trati' 						=> $predloha->stavitel_trati,
							'stavitel_trati_trida' 					=> $predloha->stavitel_trati_trida,
							'dalsi_stavitele' 						=> $predloha->dalsi_stavitele,
							'web' 									=> $predloha->web,
							'km_lesa' 								=> $predloha->km_lesa,
							'km_celkem' 							=> $predloha->km_celkem,
							'km_nezmapovaneho_lesa' 				=> $predloha->km_nezmapovaneho_lesa,
							'odpovedny_zpracovatel_mapy' 			=> $predloha->odpovedny_zpracovatel_mapy,
							'mapy_pokryvajici_prostor' 				=> $predloha->mapy_pokryvajici_prostor,
							'probehle_zavody' 						=> $predloha->probehle_zavody,
							'vlastnici_pozemku_zavod' 				=> $predloha->vlastnici_pozemku_zavod,
							'vlastnici_pozemku_shromazdiste' 		=> $predloha->vlastnici_pozemku_shromazdiste,
							'vlastnici_pozemku_parkovani' 			=> $predloha->vlastnici_pozemku_parkovani,
							'katastr_zavod' 						=> $predloha->katastr_zavod,
							'organy_ochrany_lesa' 					=> $predloha->organy_ochrany_lesa,
							'organy_ochrany_prirody' 				=> $predloha->organy_ochrany_prirody,
							'np' 									=> $predloha->np,
							'chko' 									=> $predloha->chko,
							'narodni_prirodni_rezervace' 			=> $predloha->narodni_prirodni_rezervace,
							'prirodni_rezervace' 					=> $predloha->prirodni_rezervace,
							'narodni_prirodni_pamatka' 				=> $predloha->narodni_prirodni_pamatka,
							'prirodni_pamatka' 						=> $predloha->prirodni_pamatka,
							'prirodni_park' 						=> $predloha->prirodni_park,
							'natura2000_ptaci_oblast' 				=> $predloha->natura2000_ptaci_oblast,
							'natura2000_evropsky_vyznamna_lokalita' => $predloha->natura2000_evropsky_vyznamna_lokalita,
							'registracni_cislo' 					=> $predloha->registracni_cislo,
							'jmeno' 								=> $predloha->jmeno,
							'mail' 									=> $predloha->mail,
							'pozice_v_oddile' 						=> $predloha->pozice_v_oddile,
							'poznamky' 								=> $predloha->poznamky
						)+array(
							'preference' 							=> $prihlaska->preference,
							'druh_zavodu' 							=> $sdruzeny_termin->druh_id,
							'termin' 								=> $sdruzeny_termin->id
						));

					//ID nové přihlášky
					$nova_prihlaska_id = $inserted->id;

					//Nastavení SESSION
					//směna stavu poslední přihlášky
					$this->sessionControler->changeState('submitted');

					//nastavení nového hashe
					$this->hash = $this->sessionControler->newHash($nova_prihlaska_id);

						//update hashe
						$prihlasky_table->wherePrimary($nova_prihlaska_id)->update(array(
							'hash' 					=> $this->hash,
							'sdruzena_prihlaska_id' => $prihlaska->id
						));

						//Update předchozí přihlášky
						$prihlaska->update(array(
							'sdruzena_prihlaska_id' => $nova_prihlaska_id
						));

					//určení poslední odeslané přihlášky pro účely zkopírování dat
					$this->sessionControler->setLastApplication(array(
						array(
								'id' 		=> $prihlaska->id,
								'termin_id' => $predloha->termin,
								'druh_id' 	=> $predloha->druh_zavodu
							),
						array(
								'id' 		=> $nova_prihlaska_id,
								'termin_id' => $sdruzeny_termin->id,
								'druh_id' 	=> $sdruzeny_termin->druh_id
							)
					));



					$soubory = $this->database->table('soubory')->select('MAX(nazev) AS nazev, MAX(cesta) AS cesta, url')->where('prihlaska_id ?', array($this->pId, $predloha->id))->group('url');
					if($soubory){
						$kopie = array();
						foreach ($soubory as $soubor)
						{
							$kopie[] = $soubor->toArray() + array('prihlaska_id' => $nova_prihlaska_id, 'kopie' => 1);
						}
						if (!empty($kopie)) {
							$inserted_files = $this->database->table('soubory')->insert($kopie);
						}
					}

					//změna identifikačních údajů nové přihlášky
					$this->pId = $nova_prihlaska_id;
					$this->krok = is_null($prihlaska->predloha_sdruzene_prihlasky) ? 2 : 3;

					//přesměrování
					$this->flashMessage('Přihláška odeslána ke zpracování.');
					$this->redirect('this');

				}else{
					//směna stavu poslední přihlášky
					$this->sessionControler->changeState('submitted');

					//smazání perzistentních parametrů odeslané přihlášky
					$this->pId = $this->hash = $this->krok = NULL;

					if(is_null($prihlaska->predloha_sdruzene_prihlasky)){
						//Nastavení SESSION
						//určení poslední odeslané přihlášky pro účely zkopírování dat
						$this->sessionControler->setLastApplication(array(
							array(
									'id' 		=> $prihlaska->id,
									'termin_id' => $prihlaska->termin,
									'druh_id' 	=> $prihlaska->druh_zavodu
								)
						));
					}else{
						$predloha = $this->database->table('prihlasky')->get($prihlaska->predloha_sdruzene_prihlasky);
						//Nastavení SESSION
						//určení poslední odeslané přihlášky pro účely zkopírování dat
						$this->sessionControler->setLastApplication(array(
							array(
									'id' 		=> $prihlaska->id,
									'termin_id' => $predloha->termin,
									'druh_id' 	=> $predloha->druh_zavodu
								)
						));
					}

					//přesměrovánís
					$this->flashMessage('Přihláška odeslána ke zpracování.');
					$this->redirect('Prihlaska:odeslana');
				}

			} else {
				//DB update failed
				$this->flashMessage('Při zpracování dat došlo k chybě a přihláška nebyla odeslána. Opakujte akci prosím znovu.');
			}
		} else {
			//verifyHash failed
			$this->flashMessage('Neplatný hash.');
		}
		//přesměrování pro jistotu
		$this->redirect('this');
	}


	public function createComponentDuplicateApplicationForm()
	{
		$last = $this->sessionControler->getLastApplication();
		if(count($last)==0){
			$this->flashMessage('Před zkopírováním přihlášky je potřeba dokončit všechny rozpracované žádosti.', 'error');
			$this->redirect('Prihlaska:');
		}

		$form = new Nette\Application\UI\Form;

		//vybere druh
		$prihlaska = $this->database->table('prihlasky')->select('druh_zavodu, kolo')->wherePrimary($last[0]['id'])->fetch();


		//vybere termíny odpovídající druhu
		$ds = count($last) == 2 ? ' AND souvisejici_termin IS NOT NULL' : '';
		$terminy = $this->database->table('terminy')->select('id, termin, druh_id AS druh, souvisejici_termin')->where('druh_id ? AND kolo_id ?'.$ds, $prihlaska->druh_zavodu, $prihlaska->kolo)->fetchAll();

		$arr = array();
		$posledni_terminy = array($last[0]['termin_id'], $last[count($last)-1]['termin_id']);

		foreach ($terminy as $termin) {
			$arr[$termin->id] = $this::dateNiceFormat(array($termin['termin'], NULL)).' '.$termin['termin']->format('Y');
			if(in_array($termin->id, $posledni_terminy)){ $arr[$termin->id] .= ' (*)'; $description = TRUE; }
		}


		$form->addSelect('termin', 'Termín', $arr)->setPrompt('-')->setRequired();


//
//		$form->addSelect('preference', 'Preference závodu', array(
//				'1' => 1,
//				'2' => 2,
//				'3' => 3,
//				'4' => 4,
//				'5' => 5,
//				'6' => 6,
//				'7' => 7
//			))
//			->setAttribute('class', 'cols1')
//			->setPrompt('-')
//    		->setOption('description', 'Zadá pořadatel, pokud podává více přihlášek do výběrového řízení současně a o některý závod má větší zájem (menší číslo = větší preference).');
//
//		$form->addSubmit('send','Zkopírovat');
//		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');
//		$form->onSuccess[] = [$this, 'prihlaskaFormSubmitted'];


		//$terminy = count($last)>1 ? $last
		$prihlaska_id = $last[0]['id'];


		$form->addSelect('preference', 'Preference závodu', array(
					'1' => 1,
					'2' => 2,
					'3' => 3,
					'4' => 4,
					'5' => 5,
					'6' => 6,
					'7' => 7
				))
			->setAttribute('class', 'cols1')
			->setPrompt('-');

		$form->addSubmit('send','Zkopírovat přihlášku');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');
		$form->onSuccess[] = [$this, 'duplicateApplicationFormSubmitted'];

		return $form;
	}

	public function duplicateApplicationFormSubmitted($form, $values)
	{
		//session data poslední žádosti
		$last = $this->sessionControler->getLastApplication();
		$prihlaska_id = $last[0]['id'];

		$predloha_sdruzene_prihlasky_id = isset($last[1]) ? $last[1]['id'] : NULL;

		$sdruzena_prihlaska_db = $this->database->table('prihlasky')->select('id')->wherePrimary($predloha_sdruzene_prihlasky_id)->count();

		$predloha_sdruzene_prihlasky_id = $sdruzena_prihlaska_db ? $predloha_sdruzene_prihlasky_id : NULL;

		//tabulka přihlášek
		$prihlasky_table = $this->database->table('prihlasky');

		//data přihlášky z databáze
		$prihlaska = $prihlasky_table->get($prihlaska_id);

		//konrola hashe
		$this->sessionControler->setId($prihlaska_id);
		if(!$this->sessionControler->verifyHash($prihlaska->hash)){
			$this->flashMessage('Došlo k chybě při ověření platnosti přihlášky. Váš požadavek nebyl dokončen.','error');
			$this->redirect('Prihlaska:');
		}

		//vložení nové přihlášky do databáze
		$inserted = $prihlasky_table->insert(array(
				'stav'									=> 'draft',
				'kolo'									=> $prihlaska->kolo,
				'created_at'							=> $prihlaska->created_at,
				'sdruzit_prihlasku'						=> $prihlaska->sdruzit_prihlasku,
				'druh_zavodu'							=> $prihlaska->druh_zavodu,
				'poradatel'								=> $prihlaska->poradatel,
				'poradatel_zkratka'						=> $prihlaska->poradatel_zkratka,
				'dalsi_poradatele'						=> $prihlaska->dalsi_poradatele,
				'centrum_zavodu_mapa'					=> $prihlaska->centrum_zavodu_mapa,
				'prostor_zavodu_mapa'					=> $prihlaska->prostor_zavodu_mapa,
				'prostor_zavodu'						=> $prihlaska->prostor_zavodu,
				'popis_terenu'							=> $prihlaska->popis_terenu,
				'reditel_zavodu'						=> $prihlaska->reditel_zavodu,
				'hlavni_rozhodci_registracni_cislo'		=> $prihlaska->hlavni_rozhodci_registracni_cislo,
				'hlavni_rozhodci'						=> $prihlaska->hlavni_rozhodci,
				'hlavni_rozhodci_trida'					=> $prihlaska->hlavni_rozhodci_trida,
				'stavitel_trati_registracni_cislo'		=> $prihlaska->stavitel_trati_registracni_cislo,
				'stavitel_trati'						=> $prihlaska->stavitel_trati,
				'stavitel_trati_trida'					=> $prihlaska->stavitel_trati_trida,
				'dalsi_stavitele'						=> $prihlaska->dalsi_stavitele,
				'km_lesa' 								=> $prihlaska->km_lesa,
				'km_celkem' 							=> $prihlaska->km_celkem,
				'km_nezmapovaneho_lesa' 				=> $prihlaska->km_nezmapovaneho_lesa,
				'web' 									=> $prihlaska->web,
				'odpovedny_zpracovatel_mapy'			=> $prihlaska->odpovedny_zpracovatel_mapy,
				'mapy_pokryvajici_prostor'				=> $prihlaska->mapy_pokryvajici_prostor,
				'probehle_zavody'						=> $prihlaska->probehle_zavody,
				'vlastnici_pozemku_zavod'				=> $prihlaska->vlastnici_pozemku_zavod,
				'vlastnici_pozemku_shromazdiste'		=> $prihlaska->vlastnici_pozemku_shromazdiste,
				'vlastnici_pozemku_parkovani'			=> $prihlaska->vlastnici_pozemku_parkovani,
				'katastr_zavod'							=> $prihlaska->katastr_zavod,
				'organy_ochrany_lesa'					=> $prihlaska->organy_ochrany_lesa,
				'organy_ochrany_prirody'				=> $prihlaska->organy_ochrany_prirody,
				'np'									=> $prihlaska->np,
				'chko'									=> $prihlaska->chko,
				'narodni_prirodni_rezervace'			=> $prihlaska->narodni_prirodni_rezervace,
				'prirodni_rezervace'					=> $prihlaska->prirodni_rezervace,
				'narodni_prirodni_pamatka'				=> $prihlaska->narodni_prirodni_pamatka,
				'prirodni_pamatka'						=> $prihlaska->prirodni_pamatka,
				'prirodni_park'							=> $prihlaska->prirodni_park,
				'natura2000_ptaci_oblast'				=> $prihlaska->natura2000_ptaci_oblast,
				'natura2000_evropsky_vyznamna_lokalita'	=> $prihlaska->natura2000_evropsky_vyznamna_lokalita,
				'registracni_cislo'						=> $prihlaska->registracni_cislo,
				'jmeno'									=> $prihlaska->jmeno,
				'mail'									=> $prihlaska->mail,
				'pozice_v_oddile'						=> $prihlaska->pozice_v_oddile,
				'poznamky'								=> $prihlaska->poznamky
			)+array(
				'termin'								=> $values->termin,
				'preference'							=> $values->preference,
				'predloha_sdruzene_prihlasky'			=> $predloha_sdruzene_prihlasky_id
			));

		//id vložené přihlášky
		$nova_prihlaska_id = $inserted->id;
		$this->pId = $nova_prihlaska_id;

		//nastavení nového hashe
		$this->hash = $this->sessionControler->newHash($nova_prihlaska_id);

		//update hashe
		$prihlasky_table->wherePrimary($nova_prihlaska_id)->update(array(
			'hash' => $this->hash
		));

		//zkopírování souborů
		$soubory = $this->database->table('soubory')->select('nazev, cesta, url')->where('prihlaska_id ?', $prihlaska->id);
		if(count($soubory)>0){
			$kopie = array();
			foreach ($soubory as $soubor)
			{
				$kopie[] = $soubor->toArray() + array('prihlaska_id' => $nova_prihlaska_id, 'kopie' => 1);
			}
			$inserted_files = $this->database->table('soubory')->insert($kopie);
		}

		//nastavení kroku
		$this->krok = 3;

		//smazání poslední žádosti ze SESSION
		$this->sessionControler->removeLastApplication();

		//přesměrování na duplikovaný formulář
		$this->redirect('Prihlaska:nova');
	}

	/**
	 * Odstrani nedokoncenou prihlasku
	 */
	public function handleRemovePendingApplication($pId, $hash)
	{
		$this->sessionControler->setId($pId);
		$prihlaska = $this->database->table('prihlasky')->select('stav')->wherePrimary($pId)->fetch();

		if(!$prihlaska){
			$this->pId = $this->hash = NULL;
			$this->sessionControler->clearPrihlaskySection($pId);
			$this->flashMessage('Přihláška (id: '.$pId.') již byla odstraněna.','error');
			$this->redirect('Prihlaska:');
		}

		if($this->verifyHash($hash)){

			if($prihlaska->stav != 'draft'){
				$this->pId = $this->hash = NULL;
				$this->flashMessage('Nelze odstranit přihlášku (id: '.$pId.'). Tato přihláška již byla odeslána ke zpracování.','error');
				$this->redirect('Prihlaska:');
			}

			$this->database->table('prihlasky')->wherePrimary($pId)->delete();
			$soubory = $this->database->table('soubory')->where('prihlaska_id',$pId);

			foreach ($soubory as $soubor) {
				//pokud nejde o kopii záznamu db (kopie != 1), smaže soubor z disku
				if($soubor->kopie === 0){
					Nette\Utils\FileSystem::delete($soubor->cesta);
				}
			}

			$soubory->delete();
		}

		$prihlaska_id = ($pId) ? $pId : NULL;
		$this->sessionControler->clearPrihlaskySection($prihlaska_id);

		$this->pId = $this->hash = $this->krok = NULL;
		$this->flashMessage('Přihláška byla odstraněna.','info');
		$this->redirect('Prihlaska:');
	}


	/**
	 * smazání poslední žádosti ze SESSION
	 */
	public function handleRemoveLastApplication()
	{
		$this->sessionControler->removeLastApplication();


		$this->pId = $this->hash = $this->krok = NULL;

		$this->flashMessage('Příkaz byl úspěšně proveden.','info');
		$this->redirect('Prihlaska:');
	}

	/**
	 * Vykresli sablonu pohledu detail.
	 */
	public function renderDetail($id, $secret)
	{
		$prihlaska = $this->database->table('prihlasky')->where('prihlasky.id ? AND kolo.do >= CURDATE()', $id)->limit(1)->fetch();

		if(!$prihlaska){
			$this->flashMessage('Omlouváme se, ale požadovaná přihláška nebyla nalezena v databázi.','error');
			$this->redirect('Prihlaska:');
		}

		if($secret != $prihlaska->id.$prihlaska->hash){
			$this->flashMessage('Je nám líto, ale zadaná adresa není platná.','error');
			$this->forward('Prihlaska:');
		}

		//Nastavíme platnost ověření na FALSE
		$this->template->access_gained = FALSE;

		//Zjistíme, jestli uživatlel již ověřil platnost a příp. ji změníme
		$session_review = $this->getSession('review-access');
		$access = $session_review->application_access;
		if($access && (isset($access['reg_no']) && isset($access['mail']))){
			if($access['reg_no'] == $prihlaska->registracni_cislo && $access['mail'] == $prihlaska->mail)
			{
				$this->template->access_gained = TRUE;
			}
		}

		$k = $this->database->table('kola')->get($prihlaska->kolo);
		if($k->do->getTimestamp() < time()){
			//$this->redirect('AktualniVr:Detail',array('id'=>$id));
			$this->redirect('AktualniVr:');
		}

		#$this->template->allow_view = $allow_view;
		$this->template->prihlaska = $prihlaska;

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


		$this->template->relevant = $this->database->table('prihlasky')->select('prihlasky.id AS id, prihlasky.mail AS mail, prihlasky.hash AS hash, druh_zavodu.druh_zkratka AS druh, prihlasky.preference AS preference, prihlasky.prostor_zavodu AS prostor, termin.termin AS termin')->where('prihlasky.poradatel_zkratka ? AND prihlasky.stav ? AND kolo.do >= CURDATE()', $prihlaska->poradatel_zkratka, array('submitted','confirmed'))->order('termin.termin DESC');
	}



	/**
	 * Fomrular pro zadani mailu a reg. cisla pro upravu prihlasky
	 */
	protected function createComponentGainAccessForm()
	{
		$form = new Nette\Application\UI\Form;
		$form->addText('reg_no', 'Registrační číslo:')
			->setRequired('Prosím zadejte registrační číslo, které jste vyplnili při vytvoření žádosti.');

		$form->addText('mail', 'E-mail:')
			->setRequired('Prosím zadejte e-mailovou adresu, kterou jste vyplnili při vytvoření žádosti.');

		$form->addSubmit('send', 'Ověřit přístup')
			->setAttribute('class','btn');

		// call method gainAccessFormSucceeded() on success
		$form->onSuccess[] = [$this, 'gainAccessFormSucceeded'];
		return $form;
	}

	/**
	 * Nastavi session pro upravu prihlasky. Kontrolu udaju provadi actionUpravit().
	 */
	public function gainAccessFormSucceeded($form, $values)
	{
		$session_review = $this->getSession('review-access');
		$session_review->application_access = array('reg_no' => Nette\Utils\Strings::upper($values->reg_no), 'mail' => $values->mail);
		$this->redirect('this');
	}

	/**
	 * Smaze session pro pristup k uprave prihlasky
	 */
	public function handleRejectAccess()
	{
		$session_review = $this->getSession('review-access');
		unset($session_review->application_access);
		$this->flashMessage('Platnost ověření byla zrušena.','success');
		$this->redirect('this');
	}

	public function renderUpravit()
	{
		$this->template->loadMapsAPI = true;
	}

	/**
	 * Zkontroluje, jestli lze prihlasku upravit
	 * a nastavi vychozi hodnoty editacniho formulare
	 */
	public function actionUpravit($pId, $secret)
	{

		//Získáme přihlášku z Databáze
		$prihlaska = $this->database->table('prihlasky')->where('prihlasky.id ?', $pId)->limit(1);

		//
		//  Pokud nemám dostatečné pravomoci,
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
			$this->flashMessage('Omlouváme se, ale daná přihláška neexistuje, nebo nemáte dostatečná oprávnění na její úpravy.','error');
			$this->forward('Prihlaska:');
		}

		//ověříme tajný kód
		if($secret != $prihlaska->id.$prihlaska->hash){
			$this->flashMessage('Je nám líto, ale zadaná adresa není platná.','error');
			$this->forward('Prihlaska:');
		}

		//Nastavíme platnost ověření na FALSE
		$this->template->access_gained = FALSE;

		//Zjistíme, jestli uživatlel již ověřil platnost a příp. ji změníme
		$session_review = $this->getSession('review-access');
		$access = $session_review->application_access;
		if($access && (isset($access['reg_no']) && isset($access['mail']))){
			if($access['reg_no'] == $prihlaska->registracni_cislo && $access['mail'] == $prihlaska->mail)
			{
				$this->template->access_gained = TRUE;
			}
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


	/**
	 *
	 * TODO
	 */
	protected function createComponentAccessRequestForm()
	{
		$form = new Nette\Application\UI\Form;

		$form->addText('mail', 'E-mail:')
			->setRequired('Prosím zadejte e-mailovou adresu, kterou jste vyplnili při vytvoření žádosti.');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu.');
		$form->addSubmit('send', 'Odeslat přistupový link')
			->setAttribute('class','btn small');

		// call method gainAccessFormSucceeded() on success
		$form->onSuccess[] = [$this, 'accessRequestFormSucceeded'];
		return $form;
	}
	/***/
	public function accessRequestFormSucceeded($form, $values)
	{
		try {
			$data = $this->accessControler->makeRequest($values->mail);
		} catch (\Exception $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('this');
		}

		$template = $this->createTemplate();
		$template->data = $data;
		$template->setFile(__DIR__ . '/templates/components/accessRequest.mail.latte');

		$mail = new Nette\Mail\Message;
		$mail->setFrom('Výběrové řízení OB <vybriz@orientacnisporty.cz>')
			->addTo($data['mail'] . ' <'.$data['mail'].'>')
			->setHtmlBody($template);

		try {
			$this->mailer->send($mail);
			$this->flashMessage('Poslali jsme vám e-mail s přístupovým odkazem. Zkontroluje si schránku.');
		} catch (\Exception $e) {
			$this->flashMessage('E-mail s odkazem nemohl být odeslán, kontaktujte prosím spávce systému. (' . $e->getMessage() . ')', 'error');
		}
		$this->redirect('Prihlaska:default');
	}
	/***/
	public function actionAccessRequest($id = 0, $mail = '', $token = '')
	{
		if ($this->accessControler->verify($id, $mail, $token)) {
			$this->flashMessage('Přístup byl ověřen.');
		} else {
			$this->flashMessage('Přístup nebyl ověřen. Zkuste formulář odeslat znovu.', 'error');
		}
		$this->redirect('Prihlaska:default');
	}
	/***/
	public function handleDeleteAccessRequest()
	{
		$this->accessControler->deleteAccessData();
		$this->flashMessage('Přístup byl zrušen.');
		$this->redirect('this');
	}


}
