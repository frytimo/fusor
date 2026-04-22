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

// Detect whether Fusor is loaded from the local app path or from a Composer vendor folder.
if (!defined('FUSOR_INSTALLATION_CONTEXT')) {
	$context = basename(dirname(FUSOR_DIR, 2)) === 'vendor' ? 'vendor' : 'local';
	define('FUSOR_INSTALLATION_CONTEXT', $context);
}

function fusor_bootstrap_project_root_override(): string {
	$override = trim((string) ($_ENV['FUSOR_PROJECT_ROOT'] ?? getenv('FUSOR_PROJECT_ROOT') ?: ''));
	if ($override !== '') {
		return rtrim($override, '/');
	}

	$env_file = FUSOR_DIR . '/.env';
	if (!is_file($env_file)) {
		return '';
	}

	$parsed = @parse_ini_file($env_file, true, INI_SCANNER_RAW);
	if (!is_array($parsed)) {
		return '';
	}

	$bootstrap_paths = $parsed['bootstrap_paths'] ?? null;
	if (!is_array($bootstrap_paths)) {
		return '';
	}

	$project_root = $bootstrap_paths['PROJECT_ROOT'] ?? $bootstrap_paths['project_root'] ?? null;
	if (!is_scalar($project_root)) {
		return '';
	}

	$normalized = trim((string) $project_root, " \n\r\t\v\x00\"'");
	if ($normalized === '') {
		return '';
	}

	return rtrim($normalized, '/');
}

// Declare PROJECT_ROOT_DIR using the detected installation layout or an explicit override.
if (!defined('PROJECT_ROOT_DIR')) {
	$project_root_override = fusor_bootstrap_project_root_override();
	if ($project_root_override !== '') {
		define('PROJECT_ROOT_DIR', rtrim($project_root_override, '/'));
	} else if (FUSOR_INSTALLATION_CONTEXT === 'vendor') {
		define('PROJECT_ROOT_DIR', dirname(FUSOR_DIR, 3));
	} else {
		define('PROJECT_ROOT_DIR', dirname(FUSOR_DIR, 2));
	}
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
