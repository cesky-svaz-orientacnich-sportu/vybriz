<?php

namespace App\Components;

use Nette;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Utils\Paginator;
use App\Model\ContentRepository;


class ContentControl extends Control
{

	/** @var ContentRepository */
	private $contentRepository;

	public function __construct(ContentRepository $contentRepository)
	{
		$this->contentRepository = $contentRepository;
	}

	public function render($id)
	{
		$container = $this->contentRepository->getOne($id);
		if ($container) {
			echo $container->content;
		}
	}

}


interface IContentControl
{
    /**
     * @return \App\Components\ContentControl
     */
    public function create();
}

