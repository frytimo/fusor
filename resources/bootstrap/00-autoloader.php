<?php

//Global Namespace for Fusor

// This file is responsible for loading the autoloader and any necessary setup for the Fusor application.
// It is included by the main bootstrap file (app/fusor/bootstrap.php) after checking the PHP version.

// Load the Composer autoloader if it exists.
$composer_autoloader_file = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composer_autoloader_file)) {
	require_once $composer_autoloader_file;
} else {
	trigger_error('Composer autoload file not found. Please run "composer install" to set up the dependencies.', E_USER_WARNING);
	// Continue loading without the Composer autoloader
}

// Load the enhanced autoloader replacement from Fusor, which supports PSR-4, classmap, and attribute-based autoloading.
$autoload_file = dirname(__DIR__) . '/classes/auto_loader.php';
require_once $autoload_file;

global $autoload;
$autoload = new auto_loader();
