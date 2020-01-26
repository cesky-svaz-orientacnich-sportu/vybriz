<?php

namespace App\AdminModule\Presenters;

use Nette,
	Nette\Utils,
	App\Model;

/**
 * Dashboard presenter.
 */
class DashboardPresenter extends BaseAdminPresenter
{

	/** @var Nette\Database\Context */
	private $database;

	/** @var Model\Caching\Storage */
	private $storage;

	public function __construct(
		Nette\Database\Context $database,
		Nette\Caching\IStorage $storage
	) {
		$this->database = $database;
		$this->storage = $storage;
	}


	public function renderDefault()
	{
		$endora_link = 'https://webadmin.endora.cz/api/xml/key/YjJwdFkzTjhlbU11WlRrdWVtbHlZbmwy';
		$cache = new Nette\Caching\Cache($this->storage);
		$this->template->endora = $cache->save('EndoraApi', function() use ($endora_link) {
			try {
				$data = Utils\Json::decode(file_get_contents($endora_link), 1);
				return $data;
			} catch (\Exception $e) {
				return [];
			}
		}, [Nette\Caching\Cache::EXPIRE => '48 hours']);

		$rok = $this->database->table('kola')->order('rok DESC')->limit(1)->fetch()->offsetGet('rok');
		$this->template->rok = $rok;
		$this->template->kola = $this->database->table('kola')->where('rok', $rok);
		$this->template->pocty_prihasek = $this->database->table('prihlasky')
			->alias('kolo','k')->select('k.id AS id, COUNT(*) AS cnt')
			->group('k.id')->where('stav != ?', 'draft')
			->where('k.rok', $rok)->fetchPairs('id', 'cnt');
	}



}
