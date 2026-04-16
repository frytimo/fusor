#!/bin/env php
<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$root_require_path = PROJECT_ROOT_DIR . '/resources/require.php';
if (is_file($root_require_path)) {
	require_once $root_require_path;
}

if (!class_exists('fusor_service', false)) {
	class_alias(\Frytimo\Fusor\resources\classes\fusor_service::class, 'fusor_service');
}

try {
	$service = \Frytimo\Fusor\resources\classes\fusor_service::create();
	exit($service->run());
} catch (Throwable $ex) {
	echo "Error occurred in " . $ex->getFile() . ' (' . $ex->getLine() . '):' . $ex->getMessage();
	exit($ex->getCode() ?: 1);
}

