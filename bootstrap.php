<?php

namespace fusor;

// Fusor is not compatible with PHP versions below 8.4, so we check the version before loading any files.
if (version_compare(PHP_VERSION, '8.4', '<')) {
	trigger_error('Fusor requires PHP 8.4 or higher.', E_USER_WARNING);
	return;
}

$files = glob(__DIR__ . '/resources/bootstrap/*.php');
sort($files);

foreach ($files as $filename) {
	require_once $filename;
}


