<?php

// Global namespace

$composer_candidates = array_values(array_unique([
	FUSOR_DIR . '/vendor/autoload.php',
	PROJECT_ROOT_DIR . '/vendor/autoload.php',
	dirname(PROJECT_ROOT_DIR) . '/vendor/autoload.php',
]));

foreach ($composer_candidates as $composer_autoload) {
	if (is_file($composer_autoload)) {
		require_once $composer_autoload;
		break;
	}
}
