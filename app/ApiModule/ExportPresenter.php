<?php

namespace App\ApiModule\Presenters;

use Nette,
    Nette\Utils\Json,
	App\Model;

/**
 * Export presenter.
 */
class ExportPresenter extends BaseApiPresenter
{
    const FORMAT_JSON = 'json';
    const FORMAT_XML = 'xml';

    /** @var Nette\Database\Context */
    private $database;

    private $methods = [
        'getEventCoordsList'
    ];

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


    public function renderDefault($method = NULL, $format = NULL)
    {
        $this->payload->Method = $method;
        $this->payload->Format = in_array($format, [self::FORMAT_JSON, self::FORMAT_XML]) ? $format : self::FORMAT_JSON;



        if(method_exists($this, $method) && in_array($method, $this->methods)){
            $data = call_user_func([$this, $method], $this->params);

            $this->payload->Status = 'OK';
            $this->payload->ExportCreated = date('Y-m-d H:i:s', time());
            $this->payload->Data = $data;

            //Vygenerování výstupu podle formátu (JSON|XML)
            if ($this->payload->Format === self::FORMAT_JSON) {
                $this->sendPayload();
            }elseif($this->payload->Format === self::FORMAT_XML){
                $this->sendResponse(new \App\ApiModule\Responses\XmlResponse($this->payload));
            }
        }else{
            $this->payload->Status = 'Error';
            $this->payload->Message = 'Method does not exist.';
            //$this->sendPayload();
        }

        //Nastavení šablony v případě, že dojde k chybě
        $this->template->setFile(__DIR__ . '/export.default.latte');
    }


    private function getEventCoordsList($args)
    {
        //Data - pole dat pro export
        $data = [];
        if(isset($args['year'])){
            //seznam kol vyhovujících zadaným parametrům
            $kola = $this->database->table('kola')->where('do < CURDATE() AND rok ?', (int) $args['year'])->select('id');

            //?round=3 - kolo
            if (isset($args['round'])) {
                $kola->where('kolo', substr($args['round'], 0, 4));
            }

            //ID kol
            $kola = $kola->fetchPairs(NULL, 'id');

            //Přihlášky pro daná kola
            $prihlasky = $this->database->table('prihlasky')->where('prihlasky.kolo IN(?) AND stav ?', $kola, ['submitted', 'confirmed', 'selected']);

            //?orisonly=1 - pouze spárované s ORISem
            if (isset($args['orisonly']) && $args['orisonly'] == TRUE) {
                $prihlasky->where('oris_id IS NOT NULL');
            }

            //Vytvoření pole dat pro export
            //
            // Event_XXX: [ID, OrisId, EventCentre, RaceArea]
            // 
            foreach ($prihlasky as $key => $prihlaska) {
                $data['Event_'.$key] = [
                        'ID' => $prihlaska['id'],
                        'OrisId' => $prihlaska['oris_id'],
                        'EventCentre' => Json::decode($prihlaska['centrum_zavodu_mapa'], TRUE),
                        'RaceArea' => Json::decode($prihlaska['prostor_zavodu_mapa'], TRUE),
                    ];
            }
        }else{
            //Pokud není nastavený povinný parametr 'year'
            $this->payload->Status = 'Error';
            $this->payload->Message = 'Missing argument';
        }

        return $data;
    }



}
