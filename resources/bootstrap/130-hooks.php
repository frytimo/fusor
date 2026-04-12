<?php

// Global Namespace for Fusor Resources

global $autoload;

if (!isset($autoload) || !$autoload instanceof \auto_loader) {
	$core_autoloader_file = dirname(__DIR__, 4) . '/resources/classes/auto_loader.php';
	if (is_file($core_autoloader_file)) {
		require_once $core_autoloader_file;
		$autoload = new \auto_loader();
	}
}

if (PHP_SAPI === 'cli') {
	return;
}

if (empty($_SERVER['SCRIPT_NAME'])) {
	return;
}

if (class_exists('frytimo\fusor\resources\classes\fusor_dispatcher')) {
	\frytimo\fusor\resources\classes\fusor_dispatcher::register_discovered_listeners($autoload, true);
}

require_once dirname(__DIR__) . '/classes/fusor_discovery.php';
require_once dirname(__DIR__) . '/classes/fusor_dispatcher.php';

if (isset($autoload) && $autoload instanceof \auto_loader && !headers_sent()) {
	$page = basename($_SERVER['SCRIPT_NAME'], '.php');
	$before_event = "before_render_$page";
	$after_event = "after_render_$page";
	$has_before_listeners = \frytimo\fusor\resources\classes\fusor_dispatcher::has_listeners($before_event);
	$has_after_listeners = \frytimo\fusor\resources\classes\fusor_dispatcher::has_listeners($after_event);

	$fusor_buffer_base_level = ob_get_level();
	$fusor_buffer_target_level = $fusor_buffer_base_level + 1;
	if ($has_after_listeners) {
		ob_start();
	}

	// check if the current page has any listeners registered for "before_$page" and if so, start output buffering to capture the output for the shutdown function
	if ($has_before_listeners) {
		$output = '';
		$event = new \frytimo\fusor\resources\classes\fusor_event($before_event, data: ['html' => &$output]);
		\frytimo\fusor\resources\classes\fusor_dispatcher::dispatch($event);
	}

	if ($has_after_listeners) {
		// register shutdown function to execute hooks for the current page after all output has been generated
		register_shutdown_function(function() use ($after_event, $fusor_buffer_base_level, $fusor_buffer_target_level) {
			$output = '';
			while (ob_get_level() > $fusor_buffer_base_level) {
				if (ob_get_level() === $fusor_buffer_target_level) {
					$output = ltrim((string) ob_get_contents());
					ob_end_clean();
					continue;
				}

				ob_end_flush();
			}

			$event = new \frytimo\fusor\resources\classes\fusor_event($after_event, data: ['html' => &$output]);
			\frytimo\fusor\resources\classes\fusor_dispatcher::dispatch($event);
			echo $output;
		});
	}
}
