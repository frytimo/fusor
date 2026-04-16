<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

// Force refresh to get new cache version
\Frytimo\Fusor\resources\classes\fusor_discovery::discover_attributes($autoload, true);

// Get methods with the current on attribute
$methods = \Frytimo\Fusor\resources\classes\fusor_discovery::get_methods('frytimo\\fusor\\resources\\attributes\\on');

echo "============ Methods with on attribute ============\n\n";
foreach ($methods as $method) {
	echo "Class: " . $method['class'] . "\n";
	echo "Method: " . $method['method'] . "\n";
	echo "Is Static: " . ($method['is_static'] ? 'yes' : 'no') . "\n";
	echo "Visibility: " . $method['visibility'] . "\n";
	echo "Attribute: " . $method['attribute'] . "\n";
	echo "Arguments (parsed as array): ";
	var_dump($method['arguments']);
	echo "Arguments Raw (original string): " . $method['arguments_raw'] . "\n";
	echo "---\n\n";
}
