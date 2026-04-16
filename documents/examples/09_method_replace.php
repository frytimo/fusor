<?php

/**
 * Example 09: The #[method_replace] Attribute — Replace a Method
 *
 * The #[method_replace] attribute completely replaces the behavior of a
 * target method. The original method still runs (via the wrapper), but
 * your handler's return value takes precedence.
 *
 * WARNING: This is the most invasive hook type. The original method DOES
 * run, but its return value is replaced by yours. Use with caution.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *   - Target must be a public STATIC method
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * CONTEXT ARRAY:
 *   $context['phase']     = 'replace'
 *   $context['target']    = 'ClassName::methodName'
 *   $context['arguments'] = [arg1, arg2, ...]
 *   $context['result']    = mixed (original return value — method still executes)
 *
 * IMPORTANT NOTES:
 *   - The original method IS called (for side effects like DB writes).
 *   - Your handler's non-null return value replaces the return though.
 *   - If your handler returns null, the original return is preserved.
 *   - method_replace and method_around use the same wrapper mechanism.
 *     The difference is semantic — "replace" signals intent to override.
 *
 * @see app/fusor/resources/attributes/method_replace.php
 * @see app/fusor/resources/classes/fusor_uopz.php (install_return_wrapper)
 */

use Frytimo\Fusor\resources\attributes\method_replace;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_09_method_replace {

	/**
	 * Replace the return value of a toggle operation.
	 *
	 * The original conferences::toggle() runs and performs the database
	 * update, but we replace the return value. This can be used to
	 * signal success/failure to the calling code.
	 */
	#[method_replace(target: 'conferences::toggle')]
	public static function replace_conferences_toggle(fusor_event $event): void {
		$original_result = $event->result ?? null;

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_09] Replaced conferences::toggle — original result: ' . json_encode($original_result));
		closelog();

		// The original method has already run. We can:
		// 1. Return null to pass through the original result
		// 2. Return a new value to replace it
		// return 'custom_result';
	}

	/**
	 * Replace a static utility method.
	 *
	 * For methods that produce a return value you need to override,
	 * return your replacement from the handler. The original still
	 * executes for any side effects.
	 */
	#[method_replace(target: 'user_logs::add')]
	public static function replace_user_log_add(array $context): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_09] Intercepted user_logs::add');
		closelog();
	}
}
