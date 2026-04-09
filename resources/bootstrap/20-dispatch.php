<?php

require_once dirname(__DIR__) . '/classes/fusor_discovery.php';
require_once dirname(__DIR__) . '/classes/fusor_dispatcher.php';

global $autoload;

if (isset($autoload) && $autoload instanceof \auto_loader) {
	\fusor\resources\classes\fusor_dispatcher::register_discovered_listeners($autoload);
}