<?php

// Global Namespace for Fusor Resources

global $autoload;

$allow_cli = false;
$env_file = dirname(__DIR__, 2) . '/.env';
if (is_file($env_file)) {
	$env = @parse_ini_file($env_file, true, INI_SCANNER_RAW);
	if (is_array($env)) {
		$raw_value = strtolower(trim((string) ($env['http_route_hooks']['allow_cli'] ?? 'false')));
		$allow_cli = in_array($raw_value, ['1', 'true', 'yes', 'on'], true);
	}
}

if (!isset($autoload) || !$autoload instanceof \auto_loader) {
	$core_autoloader_file = dirname(__DIR__, 4) . '/resources/classes/auto_loader.php';
	if (is_file($core_autoloader_file)) {
		require_once $core_autoloader_file;
		$autoload = new \auto_loader();
	}
}

if (PHP_SAPI === 'cli' && !$allow_cli) {
	return;
}

if (empty($_SERVER['REQUEST_METHOD'])) {
	return;
}

if (!isset($autoload) || !$autoload instanceof \auto_loader) {
	return;
}

require_once dirname(__DIR__) . '/classes/http_route_hook_dispatcher.php';

\frytimo\fusor\resources\classes\http_route_hook_dispatcher::dispatch_request_hooks($autoload, true);
