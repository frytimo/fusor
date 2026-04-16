<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

//Get the raw entry from auto_loader
$autoload->update();
$attrs = $autoload->get_attributes();
$methods = $attrs['method'] ?? [];
$entry_key = array_key_first($methods);

if ($entry_key === null || !isset($methods[$entry_key][0]) || !is_array($methods[$entry_key][0])) {
	echo "FAIL: no method entries available for normalize test\n";
	exit(1);
}

$entry = $methods[$entry_key][0];

echo "Original entry from auto_loader:\n";
var_dump($entry);

// Now manually call normalize_entry through reflection to see what it returns
$reflection = new ReflectionClass('\\frytimo\\fusor\\resources\\classes\\fusor_discovery');
$method = $reflection->getMethod('normalize_entry');
$method->setAccessible(true);

$normalized = $method->invokeArgs(null, ['method', $entry_key, $entry]);

echo "\nNormalized entry:\n";
var_dump($normalized);
