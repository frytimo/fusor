#!/bin/env php
<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 4) . '/resources/require.php';

if (!class_exists('fusor_service', false)) {
	class_alias(\frytimo\fusor\resources\classes\fusor_service::class, 'fusor_service');
}

try {
	$service = \frytimo\fusor\resources\classes\fusor_service::create();
	exit($service->run());
} catch (Throwable $ex) {
	echo "Error occurred in " . $ex->getFile() . ' (' . $ex->getLine() . '):' . $ex->getMessage();
	exit($ex->getCode() ?: 1);
}

