<?php

// Global namespace

// Protect against multiple includes
if (!defined('FUSOR_DIR')) {
	// Fusor is not compatible with PHP versions below 8.4, so we check the version before loading any files.
	if (version_compare(PHP_VERSION, '8.4', '<')) {
		trigger_error('Fusor requires PHP 8.4 or higher.', E_USER_WARNING);
		return;
	}

	define('FUSOR_DIR', __DIR__);
	$project_root = dirname(FUSOR_DIR, 2);

	// Load Fusor bootstrap files first, then app bootstrap files project-wide.
	$fusor_bootstrap_files = glob(FUSOR_DIR . '/resources/bootstrap/*.php') ?: [];
	sort($fusor_bootstrap_files);

	$project_bootstrap_files = glob($project_root . '/*/*/resources/bootstrap/*.php') ?: [];

	$files = array_merge($fusor_bootstrap_files, $project_bootstrap_files);
	$files = array_values(array_unique($files));
	usort($files, static function (string $a, string $b): int {
		$name_compare = strcmp(basename($a), basename($b));
		if ($name_compare !== 0) {
			return $name_compare;
		}

		return strcmp($a, $b);
	});

	// Include each bootstrap file in order
	foreach ($files as $filename) {
		require_once $filename;
	}
}
