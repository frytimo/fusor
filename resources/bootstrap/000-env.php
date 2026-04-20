<?php

// Global Namespace for Fusor Resources

// Some uopz installs default uopz.exit=0, which turns exit/die into no-ops.
// FusionPBX authentication and logout flows rely on exit after redirects, so
// restore normal behavior as early as possible when Fusor is active.
if (extension_loaded('uopz')) {
	if (function_exists('uopz_allow_exit')) {
		try {
			@call_user_func('\\uopz_allow_exit', true);
		} catch (\Throwable $exception) {
			// Ignore environments that do not allow changing this at runtime.
		}
	}

	if ((string) ini_get('uopz.exit') !== '1') {
		@ini_set('uopz.exit', '1');
	}
}

/**
 * This file is responsible for loading environment variables from .env files located in the Fusor directory,
 * the project root, or the parent of the project root. It uses the env_loader class to parse the .env files
 * and set the environment variables in the $_ENV superglobal. This allows other parts of the application to
 * access configuration settings defined in .env files using $_ENV['key'] syntax.
 */

function load_env() {
	// The .env can be in the fusor directory, the project root, or the parent of the project root
	$paths = [
		FUSOR_DIR,
		PROJECT_ROOT_DIR,
		dirname(PROJECT_ROOT_DIR),
	];

	// Get settings from the .ENV file for the autoloader from this folder
	foreach ($paths as $path) {
		$env_path = $path . '/.env';
		if (file_exists($env_path)) {
			require_once FUSOR_DIR . '/resources/classes/env_loader.php';
			// Parse the .env
			env_loader::load_env_file($env_path);
			// Set the environment variables in $_ENV superglobal
			env_loader::set_env();
			break;
		}
	}

}
load_env();

//
// To access the cache env setting, use $_ENV['cache']
//
