<?php

/**
 * Example 05: The #[on_method_exit] Attribute — Post-Execution Hook
 *
 * The #[on_method_exit] attribute runs your handler AFTER the target method
 * returns. You receive the return value in the context and can optionally
 * replace it by returning a new value from your handler.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - Fusor installed at app/fusor/
 *   - uopz extension loaded and enabled
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * CONTEXT ARRAY:
 *   $context['phase']     = 'exit'
 *   $context['target']    = 'ClassName::methodName'
 *   $context['arguments'] = [arg1, arg2, ...]   — original arguments
 *   $context['result']    = mixed                — the method's return value
 *
 * RETURN VALUE BEHAVIOR:
 *   - If your handler returns a non-null value, it REPLACES the original return.
 *   - If your handler returns null (or void), the original return is kept.
 *
 * EQUIVALENT ALIASES:
 *   #[on_method_exit(target: '...')]
 *   #[on_method_after(target: '...')]
 *   #[on_method(target: '...', event_name: 'exit')]
 *   #[on_method(target: '...', event_name: 'after')]
 *
 * IMPORTANT: Exit hooks use uopz_set_return() with a wrapper closure.
 * This only works with public STATIC methods. Instance methods are not
 * supported for exit/after/around/replace hooks.
 *
 * @see app/fusor/resources/attributes/on_method_exit.php
 * @see app/fusor/resources/attributes/on_method.php (base class)
 * @see app/fusor/resources/classes/fusor_uopz.php
 */

use Frytimo\Fusor\resources\attributes\on_method_exit;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_05_on_method_exit {

	/**
	 * Log after authentication::create_user_session() completes.
	 *
	 * This fires after the session is created during a successful login.
	 * The result is available in $event->result.
	 */
	#[on_method_exit(target: 'authentication::create_user_session')]
	public static function after_session_created(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_05] authentication::create_user_session completed');
		closelog();
	}

	/**
	 * Modify a method's return value.
	 *
	 * When your exit hook returns a non-null value, it replaces the
	 * original return value. This is powerful for transforming output
	 * without modifying the original class.
	 *
	 * In this example we show the pattern with a hypothetical method.
	 * The same approach works on any static method in FusionPBX.
	 */
	#[on_method_exit(target: 'user_logs::add')]
	public static function after_user_log_add(fusor_event $event): void {
		// Log that a user action was logged
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_05] user_logs::add completed — result: ' . json_encode($event->result ?? null));
		closelog();

		// Return null to keep the original return value unchanged.
		// Return a value to replace it:
		// return 'modified result';
	}

	/**
	 * Exit hook using the raw context array.
	 *
	 * Type-hinting `array $context` gives you direct access to the
	 * context data without wrapping in fusor_event.
	 */
	#[on_method_exit(target: 'bridges::copy')]
	public static function after_bridges_copy(array $context): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_05] bridges::copy completed — phase: ' . ($context['phase'] ?? 'unknown'));
		closelog();
	}
}
