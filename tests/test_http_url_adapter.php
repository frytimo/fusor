<?php

$fusor_root = dirname(__DIR__);
require_once $fusor_root . '/bootstrap.php';

global $autoload;

if (!isset($autoload) || !($autoload instanceof auto_loader)) {
	echo "FAIL: autoloader is unavailable\n";
	exit(1);
}

\Frytimo\Fusor\resources\classes\fusor_dispatcher::clear_listeners();

$get_attribute = new \Frytimo\Fusor\resources\attributes\http_get('/fusor/url-test/', 'after');
$post_attribute = new \Frytimo\Fusor\resources\attributes\http_post('/fusor/url-test/', 'before');
$empty_get_attribute = new \Frytimo\Fusor\resources\attributes\http_get();

if ($get_attribute->event_name !== 'after_http_get:/fusor/url-test') {
	echo "FAIL: expected normalized http_get attribute event name\n";
	exit(1);
}

if ($post_attribute->event_name !== 'before_http_post:/fusor/url-test') {
	echo "FAIL: expected normalized http_post attribute event name\n";
	exit(1);
}

if ($empty_get_attribute->path === '*') {
	echo "FAIL: empty http_get path must not behave as a global wildcard\n";
	exit(1);
}

$captured_get_event = null;
$captured_post_event = null;

\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_listener(
	'before_http_get:/fusor/url-test',
	static function (\Frytimo\Fusor\resources\classes\fusor_event $event) use (&$captured_get_event): void {
		$captured_get_event = $event;
	}
);

\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_listener(
	'before_http_post:/fusor/url-test',
	static function (\Frytimo\Fusor\resources\classes\fusor_event $event) use (&$captured_post_event): void {
		$captured_post_event = $event;
	}
);

$original_server = $_SERVER ?? [];
$original_get = $_GET ?? [];
$original_post = $_POST ?? [];
$original_request = $_REQUEST ?? [];

try {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/fusor/url-test?name=Tim+Fry&unsafe=%3Cscript%3Ebad%3C%2Fscript%3E&page=2';
	$_SERVER['SCRIPT_NAME'] = '/index.php';
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
	$_GET = [
		'name' => 'Tim Fry',
		'unsafe' => '<script>bad</script>',
		'page' => '2',
	];
	$_POST = [];
	$_REQUEST = $_GET;

	$invoked_get = \Frytimo\Fusor\resources\classes\http_route_hook_dispatcher::dispatch_request_hooks($autoload, true);
	if ($invoked_get < 1) {
		echo "FAIL: expected GET hook to be invoked\n";
		exit(1);
	}

	if (!$captured_get_event instanceof \Frytimo\Fusor\resources\classes\fusor_event) {
		echo "FAIL: expected GET fusor_event to be captured\n";
		exit(1);
	}

	if (!is_object($captured_get_event->url ?? null)) {
		echo "FAIL: expected event->url object on GET event\n";
		exit(1);
	}

	if (($captured_get_event->url->get_path() ?? null) !== '/fusor/url-test') {
		echo "FAIL: expected normalized GET path\n";
		exit(1);
	}

	if (($captured_get_event->url->get_query_param('name') ?? null) !== 'Tim Fry') {
		echo "FAIL: expected filtered GET query param\n";
		exit(1);
	}

	if (($captured_get_event->url->get_query_param('unsafe') ?? null) === '<script>bad</script>') {
		echo "FAIL: expected filtered GET query param to be sanitized\n";
		exit(1);
	}

	if (($captured_get_event->url->get_query_param('unsafe', null, true) ?? null) !== '<script>bad</script>') {
		echo "FAIL: expected unsafe GET query param\n";
		exit(1);
	}

	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI'] = '/fusor/url-test?status=created';
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) + 0.1234;
	$_GET = [
		'status' => 'created',
	];
	$_POST = [
		'username' => 'alice',
		'note' => '<b>hello</b>',
	];
	$_REQUEST = array_merge($_GET, $_POST);

	$invoked_post = \Frytimo\Fusor\resources\classes\http_route_hook_dispatcher::dispatch_request_hooks($autoload, true);
	if ($invoked_post < 1) {
		echo "FAIL: expected POST hook to be invoked\n";
		exit(1);
	}

	if (!$captured_post_event instanceof \Frytimo\Fusor\resources\classes\fusor_event) {
		echo "FAIL: expected POST fusor_event to be captured\n";
		exit(1);
	}

	if (($captured_post_event->url->get('status') ?? null) !== 'created') {
		echo "FAIL: expected URL object to expose GET query for POST request\n";
		exit(1);
	}

	if (($captured_post_event->url->post('username') ?? null) !== 'alice') {
		echo "FAIL: expected URL object to expose POST body value\n";
		exit(1);
	}

	if (($captured_post_event->url->post('note', null, true) ?? null) !== '<b>hello</b>') {
		echo "FAIL: expected URL object to expose unsafe POST body value\n";
		exit(1);
	}

	echo "PASS: HTTP URL adapter exposes safe and unsafe request access on fusor_event\n";
} finally {
	$_SERVER = $original_server;
	$_GET = $original_get;
	$_POST = $original_post;
	$_REQUEST = $original_request;
	\Frytimo\Fusor\resources\classes\fusor_dispatcher::clear_listeners();
}
