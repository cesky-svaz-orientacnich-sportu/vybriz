<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Bootstrap\Configurator;

$configurator->enableDebugger(__DIR__ . '/../log');

$configurator->setTempDirectory(realpath(__DIR__ . '/../temp'));

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon');

$container = $configurator->createContainer();

// Setup Form Replicator
Kdyby\Replicator\Container::register();

return $container;
