<?php

//Global Namespace for Fusor

// This file is responsible for loading the autoloader and any necessary setup for the Fusor application.
// It is included by the main bootstrap file (app/fusor/bootstrap.php) after checking the PHP version.

$is_preload_context = !isset($_SERVER['REQUEST_METHOD']);

// Load the Composer autoloader if it exists.

$composer_autoloader_file = null;
$composer_autoloader_candidates = [
	dirname(__DIR__, 2) . '/vendor/autoload.php',
	dirname(__DIR__, 4) . '/vendor/autoload.php',
];

foreach ($composer_autoloader_candidates as $candidate) {
	if (file_exists($candidate)) {
		$composer_autoloader_file = $candidate;
		break;
	}
}

if ($composer_autoloader_file !== null) {
	require_once $composer_autoloader_file;
} else if (!$is_preload_context) {
	trigger_error('Composer autoload file not found. Please run "composer install" to set up the dependencies.', E_USER_WARNING);
	// Continue loading without the Composer autoloader
}

if ($is_preload_context) {
	return;
}

