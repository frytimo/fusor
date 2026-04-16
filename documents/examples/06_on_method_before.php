<?php

/**
 * Example 06: The #[on_method_before] Attribute — Alias for Enter
 *
 * The #[on_method_before] attribute is a convenience alias for
 * #[on_method_enter]. They produce identical behavior. Use whichever
 * reads better in your codebase.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * "before" normalizes to "enter" internally:
 *   on_method_before → on_method(event_name: 'before')
 *                    → normalize_phase('before') → 'enter'
 *                    → uopz_set_hook()
 *
 * @see app/fusor/resources/attributes/on_method_before.php
 * @see app/fusor/resources/attributes/on_method.php (normalize_phase)
 */

use Frytimo\Fusor\resources\attributes\on_method_before;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_06_on_method_before {

	/**
	 * Run before dialplan::copy() executes.
	 *
	 * Identical behavior to #[on_method_enter(target: 'dialplan::copy')].
	 * The "before" alias is provided for semantic clarity when you prefer
	 * the before/after naming convention over enter/exit.
	 */
	#[on_method_before(target: 'dialplan::copy')]
	public static function before_dialplan_copy(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_06] Before dialplan::copy');
		closelog();
	}

	/**
	 * Run before ring_groups::delete() executes.
	 */
	#[on_method_before(target: 'ring_groups::delete')]
	public static function before_ring_group_delete(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_06] Before ring_groups::delete');
		closelog();
	}

	/**
	 * Run before gateways::toggle() executes.
	 */
	#[on_method_before(target: 'gateways::toggle', priority: 10)]
	public static function before_gateway_toggle(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_06] Before gateways::toggle (priority 10)');
		closelog();
	}
}
