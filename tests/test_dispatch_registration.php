<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

if (!isset($autoload) || !($autoload instanceof auto_loader)) {
	echo "FAIL: autoloader is unavailable\n";
	exit(1);
}

$autoload->update();

\fusor\resources\classes\missed_call_webhook_listener::reset_handled_count();
$registered = \fusor\resources\classes\fusor_dispatcher::register_discovered_listeners($autoload, true);

if ($registered < 1) {
	echo "FAIL: expected at least one discovered listener, got {$registered}\n";
	exit(1);
}

\fusor\resources\classes\fusor_dispatcher::dispatch(new \fusor\resources\classes\fusor_event('call.missed'));
$count = \fusor\resources\classes\missed_call_webhook_listener::get_handled_count();

if ($count !== 1) {
	echo "FAIL: expected listener invocation count of 1, got {$count}\n";
	exit(1);
}

\fusor\resources\classes\fusor_dispatcher::dispatch(new \fusor\resources\classes\fusor_event('call.ended'));
$count_after_non_matching = \fusor\resources\classes\missed_call_webhook_listener::get_handled_count();

if ($count_after_non_matching !== 1) {
	echo "FAIL: non-matching event should not invoke listener, got {$count_after_non_matching}\n";
	exit(1);
}

$wildcard_hits = 0;
\fusor\resources\classes\fusor_dispatcher::register_listener('call.*', static function (\fusor\resources\classes\fusor_event $event) use (&$wildcard_hits): void {
	++$wildcard_hits;
});

\fusor\resources\classes\fusor_dispatcher::dispatch(new \fusor\resources\classes\fusor_event('call.ended'));
if ($wildcard_hits !== 1) {
	echo "FAIL: expected wildcard listener to match call.ended once, got {$wildcard_hits}\n";
	exit(1);
}

echo "PASS: discovery registration and dispatch flow is working\n";
