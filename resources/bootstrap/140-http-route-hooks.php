<?php

// Global Namespace for Fusor Resources

global $autoload;

$allow_cli = filter_var($_ENV['allow_cli'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ((!$allow_cli && PHP_SAPI === 'cli')
		|| empty($_SERVER['SCRIPT_NAME'])
		|| empty($_SERVER['REQUEST_METHOD'])
		|| !isset($autoload)
		|| !$autoload instanceof \auto_loader
	) {
	return;
}

\Frytimo\Fusor\resources\classes\http_route_hook_dispatcher::dispatch_request_hooks($autoload, true);
