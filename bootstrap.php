<?php

// Global namespace

// Fusor is not compatible with PHP versions below 8.2, so we check the version before loading any files.
if (version_compare(PHP_VERSION, '8.2', '<')) {
	trigger_error('Fusor requires PHP 8.2 or higher.', E_USER_WARNING);
	return;
}

// Declare constants for Fusor directory if not already defined.
if (!defined('FUSOR_DIR')) {
	define('FUSOR_DIR', __DIR__);
}

// Declare PROJECT_ROOT_DIR as the parent directory of FUSOR_DIR if not already defined.
if (!defined('PROJECT_ROOT_DIR')) {
	define('PROJECT_ROOT_DIR', dirname(FUSOR_DIR, 2));
}

// Load Fusor bootstrap files first, then app bootstrap files project-wide.
$fusor_bootstrap_files = glob(FUSOR_DIR . '/resources/bootstrap/*.php') ?: [];
sort($fusor_bootstrap_files);

$project_bootstrap_files = glob(PROJECT_ROOT_DIR . '/*/*/resources/bootstrap/*.php') ?: [];

$files = array_merge($fusor_bootstrap_files, $project_bootstrap_files);
$files = array_values(array_unique($files));
usort($files, static function (string $a, string $b): int {
	$name_compare = strcmp(basename($a), basename($b));
	if ($name_compare !== 0) {
		return $name_compare;
	}

	return strcmp($a, $b);
});

// Include each bootstrap file in order.
// require_once prevents duplicate loads within the same request.
foreach ($files as $filename) {
	require_once $filename;
}
