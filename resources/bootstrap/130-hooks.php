<?php

global $autoload;

if (!isset($autoload) || !($autoload instanceof \auto_loader)) {
	return;
}

if (PHP_SAPI !== 'cli' && empty($_SERVER['SCRIPT_NAME']) && empty($_SERVER['REQUEST_URI'] ?? '')) {
	return;
}

\Frytimo\Fusor\resources\classes\fusor_uopz::register_discovered_hooks($autoload);
