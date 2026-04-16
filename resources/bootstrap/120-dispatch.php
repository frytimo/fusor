<?php

/**
 *
 * This file is responsible to dispatch (call) any events that have been registered for the current page by using attributes.
 * It checks if  there are any  listeners registered for the "after_$page"  events and if so,  it starts output buffering to
 * capture the output for the shutdown function.The shutdown function will then execute the hooks for the current page after
 * all  output  has been generated.  When a method has the attribute to run before the page is rendered, it will be executed
 * immediately and the output will be passed by reference to the method so it can modify the output before it is sent to the
 * browser.
 *
 */

/**
 * Dispatches events for the current page.
 *
 * @return void
 */
function dispatch() {
	$page = basename($_SERVER['SCRIPT_NAME'], '.php');
	$html_output = '';
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
		// The html is passed by reference to the event so it can be modified by the listeners before it is sent to the browser.
		$event = new \frytimo\fusor\resources\classes\fusor_event($before_event, data: ['html' => &$html_output]);
		\frytimo\fusor\resources\classes\fusor_dispatcher::dispatch($event);
	}

	if ($has_after_listeners) {
		// register shutdown function to execute hooks for the current page after all output has been generated
		register_shutdown_function(function() use ($after_event, $fusor_buffer_base_level, $fusor_buffer_target_level, &$html_output) {
			$output = '';
			while (ob_get_level() > $fusor_buffer_base_level) {
				if (ob_get_level() === $fusor_buffer_target_level) {
					$output = ltrim((string) ob_get_contents());
					ob_end_clean();
					continue;
				}

				ob_end_flush();
			}

			$event = new \frytimo\fusor\resources\classes\fusor_event($after_event, data: ['html' => &$html_output]);
			\frytimo\fusor\resources\classes\fusor_dispatcher::dispatch($event);
			echo $output;
		});
	}
}

dispatch();
