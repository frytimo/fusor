<?php

// Global Namespace for Fusor

// This file is responsible for loading the autoloader and any necessary setup for the Fusor application.
// It is included by the main bootstrap file (app/fusor/bootstrap.php) after checking the PHP version.

$is_preload_context = !isset($_SERVER['REQUEST_METHOD']);

function load_composer_autoloader() {
	$is_preload = !isset($_SERVER['REQUEST_METHOD']);

	// Load the Composer autoloader if it exists.
	$composer_autoloader_file       = null;
	$composer_autoloader_candidates = array_values(array_unique([
		FUSOR_DIR . '/vendor/autoload.php',
		PROJECT_ROOT_DIR . '/vendor/autoload.php',
		dirname(PROJECT_ROOT_DIR) . '/vendor/autoload.php',
	]));

	foreach ($composer_autoloader_candidates as $candidate) {
		if (file_exists($candidate)) {
			$composer_autoloader_file = $candidate;
			break;
		}
	}

	if ($composer_autoloader_file !== null) {
		require_once $composer_autoloader_file;
	} else if (!$is_preload) {
		trigger_error('Composer autoload file not found. Please run "composer install" to set up the dependencies.', E_USER_WARNING);
		// Continue loading without the Composer autoloader
	}

	if ($is_preload) {
		return;
	}
}

// Check if we have an override
if (!defined('AUTOLOADER_CLASS_LOADED')) {
	// Override the FusionPBX auto_loader with our enhanced version that supports attribute discovery and other features.
	require_once FUSOR_DIR . '/resources/classes/auto_loader.php';
	define('AUTOLOADER_CLASS_LOADED', true);
}

// Initialize the autoloader with caching enabled if specified in the environment variables.
$autoload = new auto_loader($_ENV['auto_loader']['cache'] ?? true);

load_composer_autoloader();
