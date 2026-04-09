<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

$attributes = $autoload->get_attributes();
var_dump($attributes);
exit;
foreach ($attributes as $attribute) {
	echo "Class: " . $attribute->class_name . "\n";
	echo "Attribute: " . $attribute->attribute_name . "\n";
	echo "Arguments:\n";
	foreach ($attribute->arguments as $argument) {
		echo "  - Name: " . $argument->name . ", Value: " . var_export($argument->value, true) . "\n";
	}
	echo "\n";
}
