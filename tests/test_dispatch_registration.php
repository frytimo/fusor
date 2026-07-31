<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

if (!isset($autoload) || !($autoload instanceof auto_loader)) {
	echo "FAIL: autoloader is unavailable\n";
	exit(1);
}

$autoload->update();

\Frytimo\Fusor\resources\classes\missed_call_webhook_listener::reset_handled_count();
$registered = \Frytimo\Fusor\resources\classes\fusor_dispatcher::register_discovered_listeners($autoload, true);

if ($registered < 1) {
	echo "FAIL: expected at least one discovered listener, got {$registered}\n";
	exit(1);
}

\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('call.missed'));
$count = \Frytimo\Fusor\resources\classes\missed_call_webhook_listener::get_handled_count();

if ($count !== 1) {
	echo "FAIL: expected listener invocation count of 1, got {$count}\n";
	exit(1);
}

\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('call.ended'));
$count_after_non_matching = \Frytimo\Fusor\resources\classes\missed_call_webhook_listener::get_handled_count();

if ($count_after_non_matching !== 1) {
	echo "FAIL: non-matching event should not invoke listener, got {$count_after_non_matching}\n";
	exit(1);
}

$wildcard_hits = 0;
\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_listener('call.*', static function (\Frytimo\Fusor\resources\classes\fusor_event $event) use (&$wildcard_hits): void {
	++$wildcard_hits;
});

\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('call.ended'));
if ($wildcard_hits !== 1) {
	echo "FAIL: expected wildcard listener to match call.ended once, got {$wildcard_hits}\n";
	exit(1);
}

$before_hits = 0;
$before_listener = static function (\Frytimo\Fusor\resources\classes\fusor_event $event) use (&$before_hits): void {
	++$before_hits;
};
\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_listener('before_http_get*', $before_listener);
$before_invoked_listeners = [];
\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('before_http_get'), $before_invoked_listeners);
\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('before_http_get:/index.php'), $before_invoked_listeners);
if ($before_hits !== 1) {
	echo "FAIL: expected before listener invocation count of 1, got {$before_hits}\n";
	exit(1);
}

$after_hits = 0;
$after_listener = static function (\Frytimo\Fusor\resources\classes\fusor_event $event) use (&$after_hits): void {
	++$after_hits;
};
\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_listener('after_http_get*', $after_listener);
$after_invoked_listeners = [];
\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('after_http_get'), $after_invoked_listeners);
\Frytimo\Fusor\resources\classes\fusor_dispatcher::dispatch(new \Frytimo\Fusor\resources\classes\fusor_event('after_http_get:/index.php'), $after_invoked_listeners);
if ($after_hits !== 1) {
	echo "FAIL: expected after listener invocation count of 1, got {$after_hits}\n";
	exit(1);
}

echo "PASS: discovery registration and dispatch flow is working\n";
