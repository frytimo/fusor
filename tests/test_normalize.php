<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

//Get the raw entry from auto_loader
$autoload->update();
$attrs = $autoload->get_attributes();
$entry = $attrs['method']['fusor\\resources\\interfaces\\switch_listener::handle'][0];

echo "Original entry from auto_loader:\n";
var_dump($entry);

// Now manually call normalize_entry through reflection to see what it returns
$reflection = new ReflectionClass('\fusor\resources\classes\fusor_discovery');
$method = $reflection->getMethod('normalize_entry');
$method->setAccessible(true);

$normalized = $method->invokeArgs(null, ['method', 'fusor\\resources\\interfaces\\switch_listener::handle', $entry]);

echo "\nNormalized entry:\n";
var_dump($normalized);
