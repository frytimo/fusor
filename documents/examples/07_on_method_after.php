<?php

/**
 * Example 07: The #[on_method_after] Attribute — Alias for Exit
 *
 * The #[on_method_after] attribute is a convenience alias for
 * #[on_method_exit]. They produce identical behavior.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * "after" normalizes to "exit" internally:
 *   on_method_after → on_method(event_name: 'after')
 *                   → normalize_phase('after') → 'exit'
 *                   → uopz_set_return() wrapper
 *
 * @see app/fusor/resources/attributes/on_method_after.php
 * @see app/fusor/resources/attributes/on_method.php (normalize_phase)
 */

use Frytimo\Fusor\resources\attributes\on_method_after;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_07_on_method_after {

	/**
	 * Run after dialplan::copy() returns.
	 *
	 * Identical behavior to #[on_method_exit(target: 'dialplan::copy')].
	 */
	#[on_method_after(target: 'dialplan::copy')]
	public static function after_dialplan_copy(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_07] After dialplan::copy — result: ' . json_encode($event->result ?? null));
		closelog();
	}

	/**
	 * Run after call_flows::delete() returns.
	 */
	#[on_method_after(target: 'call_flows::delete')]
	public static function after_call_flow_delete(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_07] After call_flows::delete');
		closelog();
	}

	/**
	 * Modify return value after ivr_menu::copy.
	 *
	 * Returning a non-null value from an after/exit hook replaces the
	 * original method's return value. This is powerful for adding
	 * side effects or transforming results.
	 */
	#[on_method_after(target: 'ivr_menu::copy')]
	public static function after_ivr_copy(fusor_event $event): void {
		// Access the original return value
		$original_result = $event->result ?? null;

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_07] After ivr_menu::copy — original result: ' . json_encode($original_result));
		closelog();

		// To replace the return value, return something other than null:
		// return 'new_value';
	}
}
