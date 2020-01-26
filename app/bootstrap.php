<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

//$configurator->setDebugMode('888.888.888.888'); // enable for your remote IP
$configurator->enableDebugger(__DIR__ . '/../log');

//$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(realpath(__DIR__ . '/../temp'));

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

/** Load config according to host name */
if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) || !isset($_SERVER['REMOTE_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')))
	$configurator->addConfig(__DIR__ . '/config/config.product.neon');
else
	$configurator->addConfig(__DIR__ . '/config/config.local.neon');

$container = $configurator->createContainer();

// Setup Form Replicator
Kdyby\Replicator\Container::register();

return $container;
