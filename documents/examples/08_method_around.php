<?php

/**
 * Example 08: The #[method_around] Attribute — Wrap a Method
 *
 * The #[method_around] attribute wraps a target method so your handler
 * runs both before AND after the original method. The original method
 * is called automatically, and you receive its result. You can modify
 * the result by returning a new value.
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
 * HOW IT WORKS:
 *   1. Your handler is registered via uopz_set_return() with execute=true.
 *   2. When the target method is called, uopz intercepts it.
 *   3. The wrapper temporarily removes itself, calls the original method,
 *      then reinstalls itself.
 *   4. Your handler receives context with both the arguments and the result.
 *   5. If you return a non-null value, it replaces the original result.
 *
 * CONTEXT ARRAY:
 *   $context['phase']     = 'around'
 *   $context['target']    = 'ClassName::methodName'
 *   $context['arguments'] = [arg1, arg2, ...]
 *   $context['result']    = mixed (original return value)
 *
 * @see app/fusor/resources/attributes/method_around.php
 * @see app/fusor/resources/classes/fusor_uopz.php (install_return_wrapper)
 */

use Frytimo\Fusor\resources\attributes\method_around;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_08_method_around {

	/**
	 * Wrap bridges::toggle() with logging on both sides.
	 *
	 * When bridges::toggle() is called:
	 *   1. uopz intercepts the call
	 *   2. The original bridges::toggle() runs first
	 *   3. Your handler receives the result
	 *   4. You can log, audit, or modify the result
	 */
	#[method_around(target: 'bridges::toggle')]
	public static function around_bridges_toggle(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_08] Around bridges::toggle — phase: ' . ($event->phase ?? 'around'));
		syslog(LOG_INFO, '[example_08] Result: ' . json_encode($event->result ?? null));
		closelog();

		// Return null to keep the original result unchanged.
		// Return a value to replace it:
		// return 'modified_result';
	}

	/**
	 * Timing wrapper — measure how long a method takes.
	 *
	 * This pattern is useful for performance monitoring. The enter part
	 * is implicit (handled by the wrapper), and you receive timing data
	 * through the original call's execution.
	 *
	 * Note: The actual timing happens inside the uopz wrapper. To add
	 * explicit timing, you'd use a static variable or shared storage.
	 */
	#[method_around(target: 'extensions::delete')]
	public static function around_extensions_delete(array $context): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_08] Around extensions::delete — args: ' . count($context['arguments'] ?? []));
		closelog();
	}
}
