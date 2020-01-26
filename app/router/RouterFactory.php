<?php

namespace App;

use Nette,
	Nette\Application\Routers\RouteList,
	Nette\Application\Routers\Route,
	Nette\Application\Routers\SimpleRouter;


/**
 * Router factory.
 */
class RouterFactory
{

	/**
	 * @return \Nette\Application\IRouter
	 */
	public function createRouter()
	{
		$router = new RouteList();
		$router[] = new Route('admin/<presenter>/<action>/<id>', [
		        'module' => 'Admin',
		        'presenter' => 'Dashboard',
		        'action' => 'default',
		        'id' => NULL,
		    ]);
		$router[] = new Route('api/<presenter>/<action>/<id>', [
		        'module' => 'Api',
		        'presenter' => 'Export',
		        'action' => 'default',
		        'id' => NULL,
		    ]);
		$router[] = new Route('<presenter>/<action>[/<id>]', 'Homepage:default');
		return $router;
	}

}
