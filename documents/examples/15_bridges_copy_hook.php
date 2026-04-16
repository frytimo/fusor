<?php

/**
 * Example 15: Bridges Copy/Delete/Toggle Hooks
 *
 * The bridges class (like most FusionPBX list classes) has three key
 * operations: copy(), delete(), and toggle(). These are instance methods
 * that take a $records array. You can hook into all three using uopz
 * enter/exit attributes.
 *
 * BRIDGES CLASS METHOD SIGNATURES:
 *   bridges::copy($records)   — duplicate selected bridge records
 *   bridges::delete($records) — delete selected bridge records
 *   bridges::toggle($records) — enable/disable selected bridge records
 *
 * THE $records ARRAY FORMAT (used by all FusionPBX list operations):
 *   [
 *       ['uuid' => 'abc-123...', 'checked' => 'true'],
 *       ['uuid' => 'def-456...', 'checked' => 'true'],
 *   ]
 *
 * OTHER CLASSES WITH THE SAME PATTERN:
 *   dialplan::copy/delete/toggle     ring_groups::copy/delete/toggle
 *   call_flows::copy/delete/toggle   gateways::copy/delete/toggle
 *   ivr_menu::copy/delete/toggle     conferences::copy/delete/toggle
 *   extensions::delete/toggle        devices::delete/toggle
 *   users::copy/delete/toggle        event_guard::copy/delete/toggle
 *   streams::copy/delete/toggle      phrases::copy/delete/toggle
 *   vars::copy/delete/toggle         number_translations::copy/delete/toggle
 *
 * Simply change the target class name to hook any of these.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * @see app/bridges/resources/classes/bridges.php
 * @see app/fusor/resources/attributes/on_method_enter.php
 * @see app/fusor/resources/attributes/on_method_exit.php
 */

use Frytimo\Fusor\resources\attributes\on_method_enter;
use Frytimo\Fusor\resources\attributes\on_method_exit;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_15_bridges_copy_hook {

	// ─── COPY HOOKS ───────────────────────────────────────────────

	/**
	 * Log before bridges::copy() executes.
	 *
	 * The arguments contain the $records array with UUIDs being copied.
	 */
	#[on_method_enter(target: 'bridges::copy')]
	public static function before_copy(fusor_event $event): void {
		$args = $event->arguments ?? [];
		$record_count = is_array($args[0] ?? null) ? count($args[0]) : 0;

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_15] bridges::copy STARTING — ' . $record_count . ' record(s)');
		closelog();
	}

	/**
	 * Log after bridges::copy() completes.
	 */
	#[on_method_exit(target: 'bridges::copy')]
	public static function after_copy(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_15] bridges::copy COMPLETED');
		closelog();
	}

	// ─── DELETE HOOKS ─────────────────────────────────────────────

	/**
	 * Log before bridges::delete() — useful for audit trails.
	 *
	 * You could also use this to back up data before deletion by
	 * querying the database for the record details.
	 */
	#[on_method_enter(target: 'bridges::delete')]
	public static function before_delete(fusor_event $event): void {
		$args = $event->arguments ?? [];
		$record_count = is_array($args[0] ?? null) ? count($args[0]) : 0;

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_WARNING, '[example_15] bridges::delete STARTING — ' . $record_count . ' record(s)');
		closelog();
	}

	/**
	 * Confirm deletion was successful.
	 */
	#[on_method_exit(target: 'bridges::delete')]
	public static function after_delete(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_15] bridges::delete COMPLETED');
		closelog();
	}

	// ─── TOGGLE HOOKS ─────────────────────────────────────────────

	/**
	 * Log enable/disable operations.
	 */
	#[on_method_enter(target: 'bridges::toggle')]
	public static function before_toggle(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_15] bridges::toggle STARTING');
		closelog();
	}

	#[on_method_exit(target: 'bridges::toggle')]
	public static function after_toggle(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_15] bridges::toggle COMPLETED');
		closelog();
	}
}
