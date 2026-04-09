<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

// Make sure autoloader is up to date
$autoload->update();

// Clear any existing cache  
\fusor\resources\classes\fusor_discovery::clear_cache();

// Discover with fresh cache
\fusor\resources\classes\fusor_discovery::discover_attributes($autoload, true);

// Get methods with 'on' attribute
$methods = \fusor\resources\classes\fusor_discovery::get_methods('on');

echo "============ Methods with 'on' attribute ============\n\n";
foreach ($methods as $method) {
	echo "Class: " . $method['class'] . "\n";
	echo "Method: " . $method['method'] . "\n";
	echo "Is Static: " . (isset($method['is_static']) ? ($method['is_static'] ? 'yes' : 'no') : 'MISSING') . "\n";
	echo "Visibility: " . (isset($method['visibility']) ? $method['visibility'] : 'MISSING') . "\n";
	echo "Arguments: " . json_encode($method['arguments']) . "\n";
	echo "---\n\n";
}
