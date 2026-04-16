<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

$canonical = '\\Frytimo\\Fusor\\resources\\classes\\fusor_event';
$legacy = '\\frytimo\\fusor\\resources\\classes\\fusor_event';

if (!defined('FUSOR_DIR') || !defined('PROJECT_ROOT_DIR')) {
	echo "FAIL: expected Fusor bootstrap constants to be defined\n";
	exit(1);
}

if (!class_exists($canonical)) {
	echo "FAIL: expected canonical namespace class to autoload\n";
	exit(1);
}

if (!class_exists($legacy)) {
	echo "FAIL: expected legacy lowercase namespace class to remain autoload-compatible\n";
	exit(1);
}

$canonical_event = new $canonical('canonical.test');
$legacy_event = new $legacy('legacy.test');

if (!$canonical_event instanceof \Frytimo\Fusor\resources\classes\fusor_event) {
	echo "FAIL: expected canonical event instance to use the canonical Fusor class\n";
	exit(1);
}

if (!$legacy_event instanceof \Frytimo\Fusor\resources\classes\fusor_event) {
	echo "FAIL: expected legacy event instance to resolve to the canonical Fusor class\n";
	exit(1);
}

echo "PASS: canonical and legacy Fusor namespaces both autoload successfully\n";
