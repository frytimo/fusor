<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

$attrs = $autoload->get_attributes();
if (isset($attrs['method'])) {
	foreach ($attrs['method'] as $target => $entries) {
		if (strpos($target, 'handle') !== false && !empty($entries)) {
			echo "Target: $target\n";
			echo "Entry keys: " . implode(', ', array_keys($entries[0])) . "\n";
			echo "is_static value: " . var_export($entries[0]['is_static'] ?? 'NOT SET', true) . "\n";
			echo "visibility value: " . var_export($entries[0]['visibility'] ?? 'NOT SET', true) . "\n";
			break;
		}
	}
}
