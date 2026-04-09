<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

// Force refresh to get new cache version
\fusor\resources\classes\fusor_discovery::discover_attributes($autoload, true);

// Get methods with listens_to attribute
$methods = \fusor\resources\classes\fusor_discovery::get_methods('fusor\\resources\\attributes\\listens_to');

echo "============ Methods with listens_to attribute ============\n\n";
foreach ($methods as $method) {
	echo "Class: " . $method['class'] . "\n";
	echo "Method: " . $method['method'] . "\n";
	echo "Is Static: " . ($method['is_static'] ? 'yes' : 'no') . "\n";
	echo "Visibility: " . ($method['visibility'] ?: '(default public)') . "\n";
	echo "Arguments: " . json_encode($method['arguments']) . "\n";
	echo "---\n\n";
}
