<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

// Make sure autoloader is up to date
$autoload->update();

// Force refresh to get new cache version
\Frytimo\Fusor\resources\classes\fusor_discovery::discover_attributes($autoload, true);

$registry = \Frytimo\Fusor\resources\classes\fusor_discovery::get_registry();
$methods = $registry['methods'] ?? [];

echo "Total methods: " . count($methods) . "\n\n";
if (!empty($methods)) {
	echo "First method entry:\n";
	print_r($methods[0]);
}
