<?php

/**
 *
 * This file is responsible to discover and register any classes that have been marked with the appropriate attributes for discovery.
 * It is common to have a 'register' function or 'route' that registers the class as a listener for a specific event or route but,
 * by using attributes, we can automate this process and reduce the amount of boilerplate code needed to register classes as listeners or routes.
 *
 */

global $autoload;

if (!isset($autoload) || !($autoload instanceof \auto_loader) || PHP_SAPI === 'cli' || empty($_SERVER['SCRIPT_NAME'])) {
	return;
}

// Discover and register any classes that have been marked with the appropriate attributes for discovery.
\Frytimo\Fusor\resources\classes\fusor_discovery::discover_attributes($autoload);
\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_discovered_listeners($autoload);
