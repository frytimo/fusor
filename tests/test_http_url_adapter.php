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
require_once $fusor_root . '/resources/classes/fusor_activated.php';
$activated_attribute = (new ReflectionMethod('fusor_activated', 'inject_marker'))->getAttributes()[0]->newInstance();

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

if (!fnmatch($activated_attribute->event_name, 'after_http_get:/core/domains/domain_json.php')) {
	echo "FAIL: Fusor activation hook should inspect PHP responses\n";
	exit(1);
}

$non_html_documents = [
	json_encode(['status' => 'ok']),
	'<?xml version="1.0"?><response>ok</response>',
	'plain text response',
	"%PDF-1.7\n",
];
foreach ($non_html_documents as $non_html_document) {
	$non_html_event = new \Frytimo\Fusor\resources\classes\fusor_event('after_http_get:/core/domains/domain_json.php', data: ['html' => $non_html_document]);
	fusor_activated::inject_marker($non_html_event);
	if (($non_html_event->data['html'] ?? null) !== $non_html_document) {
		echo "FAIL: Fusor activation hook must not modify non-HTML responses\n";
		exit(1);
	}
}

$html_document = "<!DOCTYPE html>\n<html><body><main>FusionPBX</main></body></html>";
$html_event = new \Frytimo\Fusor\resources\classes\fusor_event('after_http_get:/index.php', data: ['html' => $html_document]);
fusor_activated::inject_marker($html_event);
$activated_html = (string) ($html_event->data['html'] ?? '');
if (strpos($activated_html, 'Fusor active') === false || strpos($activated_html, 'Fusor active') > strpos($activated_html, '</body>')) {
	echo "FAIL: Fusor activation marker should be inserted into HTML responses\n";
	exit(1);
}

$captured_get_event = null;
$captured_post_event = null;
$before_get_hits = 0;

\Frytimo\Fusor\resources\classes\fusor_dispatcher::register_listener(
	'before_http_get*',
	static function (\Frytimo\Fusor\resources\classes\fusor_event $event) use (&$before_get_hits): void {
		++$before_get_hits;
	}
);

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

	if ($before_get_hits !== 1) {
		echo "FAIL: expected before GET listener invocation count of 1, got {$before_get_hits}\n";
		exit(1);
	}

	$duplicate_get = \Frytimo\Fusor\resources\classes\http_route_hook_dispatcher::dispatch_request_hooks($autoload, true);
	if ($duplicate_get !== 0 || $before_get_hits !== 1) {
		echo "FAIL: repeated request dispatch must not invoke before GET listeners again\n";
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
