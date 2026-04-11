<?php

// Global namespace

// Fusor is not compatible with PHP versions below 8.4, so we check the version before loading any files.
if (version_compare(PHP_VERSION, '8.4', '<')) {
	trigger_error('Fusor requires PHP 8.4 or higher.', E_USER_WARNING);
	return;
}

// Protect against multiple includes
if (!defined('FUSOR_DIR')) {
	define('FUSOR_DIR', __DIR__);

	// Get the files to bootstrap in the correct order and include them
	$files = glob(FUSOR_DIR . '/resources/bootstrap/*.php');
	sort($files);

	// Include each bootstrap file in order
	foreach ($files as $filename) {
		require_once $filename;
	}
}
