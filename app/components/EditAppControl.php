<?php

namespace App\Components;

use Nette,
	Nette\Application\UI,
	Nette\Utils;


/**
 * Komponenta nabídek učebnic
 *
 * @author   Ondřej Vaniš
 */

class EditAppControl extends UI\Control
{

    /** @var Nette\Database\Explorer */
    private $database;

    /** @var $prihlaska */
    private $prihlaska;

    /** Other properties */
	private $prostor_zavodu_mapa;
	private $centrum_zavodu_mapa;
	private $probehle_zavody;
	private $mapy_pokryvajici_prostor;
	/**  ----  */
	private $termin;
	private $druh;
	private $soubory;
	/**  ----  */
	private $dalsi_poradatele = array();
	private $dalsi_stavitele = array();

    /**
     * [__construct description]
     * @param NetteDatabaseContext  $database         [description]
     * @param ModelSessionControler $sessionControler [description]
     */
    public function __construct(Nette\Database\Explorer $database)
    {
        $this->database = $database;
    }


    public function setPrihlaska($prihlaska)
    {
    	$this->prihlaska = $prihlaska;

		$this->prostor_zavodu_mapa = Utils\Json::decode($prihlaska['prostor_zavodu_mapa'],1);
		$this->centrum_zavodu_mapa = Utils\Json::decode($prihlaska['centrum_zavodu_mapa'],1);
		$this->probehle_zavody = Utils\Json::decode($prihlaska['probehle_zavody'],1);
		$this->mapy_pokryvajici_prostor = Utils\Json::decode($prihlaska['mapy_pokryvajici_prostor'],1);
		$this->dalsi_stavitele = Utils\Json::decode($prihlaska['dalsi_stavitele'],1);

		$dalsi_poradatele = array();
		foreach (explode(', ', $prihlaska['dalsi_poradatele']) as $dalsi_poradatel) {
			$dalsi_poradatele[] = array('oddil_zkratka' => $dalsi_poradatel);
		}
		$this->dalsi_poradatele = $dalsi_poradatele;



    	if ($prihlaska->termin && $prihlaska->druh_zavodu) {
    		$this->termin = $this->database->table('terminy')->get($prihlaska->termin);
    		$this->druh	= $this->database->table('druhy')->get($prihlaska->druh_zavodu);
    	}

		$this->soubory = $this->database->table('soubory')->where('prihlaska_id', $prihlaska->id)->fetchAll();

    }


    public function getPrihlaska()
    {
		return array(
				'prihlaska' => $this->prihlaska,
				'dalsi_poradatele' => $this->dalsi_poradatele,
				'dalsi_stavitele' => $this->dalsi_stavitele,
				'prostor_zavodu_mapa' => $this->prostor_zavodu_mapa,
				'centrum_zavodu_mapa' => $this->centrum_zavodu_mapa,
				'probehle_zavody' => $this->probehle_zavody,
				'mapy_pokryvajici_prostor' => $this->mapy_pokryvajici_prostor,
				'termin' => $this->termin,
				'druh' => $this->druh
			);
    }

	/**
	 * Vykreslí komentářovou komponentu pomocí metody render() - automaticky volaná při vykreslování komponenty pomocí makra {control ...}.
	 *
	 * @todo     Metoda neřeší, že v $this->articleId může být NULL.
	 * @return   void
	 */
	public function render()
	{
		$this->template->setFile(__DIR__ . '/editAppForm.latte');
		$this->template->prihlaska = $this->prihlaska;

		$this->template->prostor_zavodu_mapa = $this->prostor_zavodu_mapa;
		$this->template->centrum_zavodu_mapa = $this->centrum_zavodu_mapa;
		$this->template->probehle_zavody = $this->probehle_zavody;
		$this->template->mapy_pokryvajici_prostor = $this->mapy_pokryvajici_prostor;

		$this->template->termin = $this->termin;
		$this->template->druh = $this->druh;
		$this->template->soubory = $this->soubory;

		$this->template->render();
	}



	/**
	 * Formulářová továrna pro úpravu dat.
	 *
	 * @return   UI\Form
	 */
	protected function createComponentEditAppForm()
	{
		$form = new UI\Form();

		$form->addText('registracni_cislo', 'Registrační číslo')
			->setAttribute('class', 'cols2');

		$form->addText('jmeno', 'Jméno')
			->setAttribute('class', 'cols2');

		$form->addText('mail', 'E-mail')
			->setAttribute('class', 'cols2');

		$form->addText('pozice_v_oddile', 'Pozice v klubu/oddíle')
			->setAttribute('class', 'cols2');


		$form->addSelect('druh', 'Druh závodu')
			->setDisabled(TRUE);

		$form->addSelect('termin', 'Termín závodu')
			->setDisabled(TRUE);	//'required' nastaveno při zpracování dat formuláře

		$form->addCheckbox('sdruzeny_termin', 'Po odeslání přihlášky vytvořit nový formulář pro sdružený termín');

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
			->setPrompt('-')
    		->setOption('description', 'Zadá pořadatel, pokud podává více přihlášek do výběrového řízení současně a o některý závod má větší zájem (menší číslo = větší preference).');
		


    	/////Pořadatel
    	$form->addText('poradatel', 'Pořadatel')
			->setAttribute('class', 'cols5')
				->setDisabled(TRUE);

    	$form->addText('poradatel_zkratka', 'Zkratka')
			->setAttribute('class', 'cols1')
    			//->addRule($form::LENGTH, 'Zkratka oddílu musí mít %d znaky', 3)
    				->setDisabled(TRUE);




    	/////Další pořadatelé
	    $dalsi_poradatele = $form->addDynamic('dalsi_poradatele', function (Nette\Forms\Container $dp) {

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
    	$form->addText('prostor_zavodu', 'Prostor závodu')
			->setAttribute('class', 'cols5')
    			->setRequired("Zadejte prostor závodu");

    	$form->addTextArea('popis_terenu', 'Popis terénu závodu')
			//->setAttribute('class', 'texarea-wide')
    			->setRequired("Zadejte popis terénu");



    	/////Organizátoři
    	$form->addText('reditel_zavodu', 'Ředitel závodu')
			->setAttribute('class', 'cols2')
				->setDisabled(TRUE);
    				
    	$form->addText('hlavni_rozhodci_registracni_cislo', 'Hlavní rozhodčí - registrační číslo')
			->setAttribute('class', 'cols2')
				->setDisabled(TRUE);
    				
    	$form->addText('hlavni_rozhodci', 'Hlavní rozhodčí')
			->setAttribute('class', 'cols2 noborder')
				->setDisabled(TRUE);
    				
    	$form->addText('hlavni_rozhodci_trida', 'Třída rozhodčího')
			->setAttribute('class', 'cols1 noborder')
				->setDisabled(TRUE);
    				
    	$form->addText('stavitel_trati_registracni_cislo', 'Stavitel tratí - registrační číslo')
			->setAttribute('class', 'cols2')
				->setDisabled(TRUE);
    				
    	$form->addText('stavitel_trati', 'Hlavní stavitel tratí')
			->setAttribute('class', 'cols2 noborder')
				->setDisabled(TRUE);
    				
    	$form->addText('stavitel_trati_trida', 'Třída rozhodčího')
			->setAttribute('class', 'cols1 noborder')
				->setDisabled(TRUE);
    				



    	/////Další stavitelé
	    $dalsi_stavitele = $form->addDynamic('dalsi_stavitele', function (Nette\Forms\Container $ds) {


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
    	$form->addText('web', 'Odkaz na web závodu')
			->setAttribute('class', 'cols4')
    		->setOption('description', 'Vyplní pořadatel v případě, že připravil pro VŘ i další prezentaci svého závodu.');

    	

    	/////Mapa a předchozí aktivity prostoru
    	$form->addText('km_lesa', Utils\Html::el()->setHtml('km<sup>2</sup> lesa') )
			->setAttribute('class', 'cols1')/*
    			->addCondition($form::FILLED, TRUE)
    			->addRule($form::FLOAT, 'Položka musí být číslo.')*/;

    	$form->addText('km_celkem', Utils\Html::el()->setHtml('celkem km<sup>2</sup>') )
			->setAttribute('class', 'cols1');

    	$form->addText('km_nezmapovaneho_lesa', Utils\Html::el()->setHtml('km<sup>2</sup> lesa dosud nemapovaného') )
			->setAttribute('class', 'cols1');

    	$form->addText('odpovedny_zpracovatel_mapy', 'Odpovědný zpracovatel mapy')
			->setAttribute('class', 'cols2');



    	/////Mapy pokrývající prostor
	    $mapy_pokryvajici_prostor = $form->addDynamic('mapy_pokryvajici_prostor', function (Nette\Forms\Container $m) {
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

	    $probehle_zavody = $form->addDynamic('probehle_zavody', function (Nette\Forms\Container $pz) {
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

    	$form->addTextArea('vlastnici_pozemku_zavod', 'Seznam vlastníků lesních a dalších pozemků, přes které povede závod')
			->setAttribute('class', 'texarea-wide-high');
    	$form->addTextArea('vlastnici_pozemku_shromazdiste', 'Seznam vlastníků pozemku určených pro shromaždiště')
			->setAttribute('class', 'texarea-wide');
    	$form->addTextArea('vlastnici_pozemku_parkovani', 'Seznam vlastníků pozemku určených pro parkování')
			->setAttribute('class', 'texarea-wide');
    	$form->addTextArea('katastr_zavod', 'Seznam katastrálních území, na kterých se závod bude konat')
			->setAttribute('class', 'texarea-wide');
    	$form->addTextArea('organy_ochrany_lesa', 'Seznam dotčených orgánů ochrany lesa dle zákona o lesích')
			->setAttribute('class', 'texarea-wide');
    	$form->addTextArea('organy_ochrany_prirody', 'Seznam dotčených orgánů ochrany přírody dle zákona o ochraně přírody a krajiny 114/1992')
			->setAttribute('class', 'texarea-wide');


    	/////Informace, zda se uvažovaný prostor nachází v chráněných územích (pokud ano, specifikujte)

		$form->addTextArea('np', 'Národní park')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('chko', 'CHKO')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('narodni_prirodni_rezervace', 'Národní přírodní rezervace')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('prirodni_rezervace', 'Přírodní rezervace')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('narodni_prirodni_pamatka', 'Národní přírodní památka')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('prirodni_pamatka', 'Přírodní památka')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('prirodni_park', 'Přírodní park')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('natura2000_ptaci_oblast', 'Ptačí oblast (dle Natura 2000)')
			->setAttribute('class', 'texarea-wide');
		$form->addTextArea('natura2000_evropsky_vyznamna_lokalita', 'Evropsky významná lokalita (dle Natura 2000)')
			->setAttribute('class', 'texarea-wide');



		/////Přílohy
		//$form->addTextArea('files_arr', 'Soubory');

		///Poznamky
    	$form->addTextArea('poznamky', 'Poznámky')
			->setAttribute('class', 'texarea-wide-high');





		$form->addSubmit('send','Uložit změny');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');
		$form->onSuccess[] = [$this, 'editAppFormSubmitted'];

	    return $form;
	}

	public function editAppFormSubmitted($form, $values)
	{
		$prihlaska = $this->database->table('prihlasky')->get($this->prihlaska->id);

		$dalsi_poradatele = array();
		$dp = $values['dalsi_poradatele'];
		foreach ($dp as $dalsi_poradatel) {
			if ($dalsi_poradatel['oddil_zkratka'] != "") {
				$dalsi_poradatele[] = $dalsi_poradatel['oddil_zkratka'];
			}
		}


		$update = $this->database->table('prihlasky')->wherePrimary($this->prihlaska->id)->update(array(
				'preference' => $values['preference'],

				'dalsi_poradatele' => implode(', ', $dalsi_poradatele),

				'prostor_zavodu' => $values['prostor_zavodu'],
				'popis_terenu' => $values['popis_terenu'],

				'dalsi_stavitele' => Utils\Json::encode($values['dalsi_stavitele'],1),


				'web' => $values['web'],
				'km_lesa' => $values['km_lesa'],
				'km_celkem' => $values['km_celkem'],
				'km_nezmapovaneho_lesa' => $values['km_nezmapovaneho_lesa'],
				'odpovedny_zpracovatel_mapy' => $values['odpovedny_zpracovatel_mapy'],

				'mapy_pokryvajici_prostor' => Utils\Json::encode($values['mapy_pokryvajici_prostor'],1),
				'probehle_zavody' => Utils\Json::encode($values['probehle_zavody'],1),

				'vlastnici_pozemku_zavod' => $values['vlastnici_pozemku_zavod'],
				'vlastnici_pozemku_shromazdiste' => $values['vlastnici_pozemku_shromazdiste'],
				'vlastnici_pozemku_parkovani' => $values['vlastnici_pozemku_parkovani'],
				'katastr_zavod' => $values['katastr_zavod'],
				'organy_ochrany_lesa' => $values['organy_ochrany_lesa'],
				'organy_ochrany_prirody' => $values['organy_ochrany_prirody'],
				'np' => $values['np'],
				'chko' => $values['chko'],
				'narodni_prirodni_rezervace' => $values['narodni_prirodni_rezervace'],
				'prirodni_rezervace' => $values['prirodni_rezervace'],
				'narodni_prirodni_pamatka' => $values['narodni_prirodni_pamatka'],
				'prirodni_pamatka' => $values['prirodni_pamatka'],
				'prirodni_park' => $values['prirodni_park'],
				'natura2000_ptaci_oblast' => $values['natura2000_ptaci_oblast'],
				'natura2000_evropsky_vyznamna_lokalita' => $values['natura2000_evropsky_vyznamna_lokalita'],
				'poznamky' => $values['poznamky']
			));
		if($update){
			$this->getPresenter()->flashMessage('Změny byly úspěšně uloženy do databáze.');
			$this->getPresenter()->redirect('this');
		}
	}


	public function PrihlaskaFormRemoveElementClicked(Nette\Forms\Controls\SubmitButton $button)
	{
	    // first parent is container
	    // second parent is it's replicator
	    $el = $button->parent->parent;
	    $el->remove($button->parent, TRUE);

	    $this['editAppForm']->cleanErrors();
	}

	public function handleUpdateCoords()
	{
		return $this->presenter->handleUpdateCoords();
	}



	public function handleUploadFile()
	{
		$presenter = $this->presenter;
		$presenter->hash = $this->prihlaska->hash;
		$this->presenter->handleUploadFile();
	}




	public function handleUploadFile2()
    {
		$presenter = $this->presenter;

 		if(!$presenter->user->isInRole('supervisor')){
			$resp = array('OK'=>0,'info'=>'Access denied.');
            $presenter->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
            die();
 		}


        if($this->prihlaska->hash && $this->prihlaska->id && $presenter->user->isInRole('supervisor')){
            if (empty($_FILES) || $_FILES['file']['error']) {
                $resp = array('OK'=>0,'info'=>'Failed to move uploaded file.');
                $presenter->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
                die();
            }

            $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
            $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

            $ds = DIRECTORY_SEPARATOR;
            $wwwDir = $presenter->context->parameters['wwwDir'];

            $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
            $sanitized = trim(Utils\Strings::webalize($fileName, '.', FALSE), '.-');
            $filePath = $wwwDir.$ds.'uploads'.$ds.'tmp-'.$this->prihlaska->id.'-'.$sanitized;


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
                    $presenter->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
                    die();
                }

                @fclose($in);
                @fclose($out);

                @unlink($_FILES['file']['tmp_name']);
            } else {
                $resp = array('OK'=>0,'info'=>'Failed to open output stream.','filepath'=> $filePath);
                $presenter->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
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


                $filePath2 = $wwwDir.$ds.'files'.$ds.$folder_name.$ds.$this->prihlaska->id.'-'.$t.'-'.$sanitized;
                // Strip the temp .part suffix off 
                Nette\Utils\FileSystem::rename("{$filePath}.part", $filePath2);


                $url = '/files/'.$folder_name.'/'.$this->prihlaska->id.'-'.$t.'-'.$sanitized;


                $inserted = $this->database->table('soubory')->insert(array(
                        'prihlaska_id' => $this->prihlaska->id,
                        'nazev' => $sanitized,
                        'cesta' => $filePath2,
                        'url' => $url
                    ));
                if(!$inserted){$fs::delete($filePath2);}
            }

            $resp = array('OK'=>1,'info'=>'File successfuly uploaded.','filepath'=> $filePath);
            $presenter->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
            die();


        }else{
            $resp = array('OK'=>0,'info'=>'Error occured!');
            $presenter->sendResponse(new Nette\Application\Responses\JsonResponse($resp));
            die();
        }
    }





	public function handleDeleteFile($file_id)
	{
			$file = $this->database->table('soubory')->get($file_id);

			if($file->prihlaska_id == $this->prihlaska->id){
				
				//pokud nejde o kopii záznamu db (kopie != 1), smaže soubor z disku
				if($file->kopie === 0){
					Nette\Utils\FileSystem::delete($file->cesta);
				}
				

				$file->delete();
				$this->flashMessage('Soubor „'.$file->nazev.'“ byl úspěšně odstraněn.');
			}
		    
			$this->redirect('this#section-files');
	}




	public function handleUpdatelist()
	{
		$this->template->soubory = $this->database->table('soubory')->where('prihlaska_id',$this->prihlaska->id);
		$this->redrawControl('files'); // invaliduje snippet 'header'
		$this->redrawControl('ajaxSnippetFormArea'); // invaliduje snippet 'header'
	}




}
