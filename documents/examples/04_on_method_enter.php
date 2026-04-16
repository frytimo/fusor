<?php

/**
 * Example 04: The #[on_method_enter] Attribute — Pre-Execution Hook
 *
 * The #[on_method_enter] attribute runs your handler BEFORE the target method
 * executes. This uses the uopz extension's uopz_set_hook() function to inject
 * a callback at the entry point of any public static method.
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
 * TARGET FORMAT:
 *   - Class method:  'ClassName::methodName'
 *   - Function:      'function_name'
 *
 * HANDLER SIGNATURES:
 *   - function(fusor_event $event): void   — receives event with context
 *   - function(array $context): void       — receives raw context array
 *   - function(): void                     — no parameters
 *
 * CONTEXT ARRAY:
 *   $context['phase']     = 'enter'
 *   $context['target']    = 'ClassName::methodName'
 *   $context['class']     = 'ClassName'
 *   $context['function']  = 'methodName'
 *   $context['arguments'] = [arg1, arg2, ...]   — arguments being passed
 *   $context['result']    = null                 — always null on enter
 *
 * EQUIVALENT ALIASES:
 *   #[on_method_enter(target: '...')]
 *   #[on_method_before(target: '...')]
 *   #[on_method(target: '...', event_name: 'enter')]
 *   #[on_method(target: '...', event_name: 'before')]
 *
 * @see app/fusor/resources/attributes/on_method_enter.php
 * @see app/fusor/resources/attributes/on_method.php (base class)
 * @see app/fusor/resources/classes/fusor_uopz.php
 * @see app/fusor/resources/bootstrap/130-hooks.php
 */

use Frytimo\Fusor\resources\attributes\on_method_enter;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_04_on_method_enter {

	/**
	 * Log when authentication::validate() is about to run.
	 *
	 * The authentication class is in the global namespace. Its validate()
	 * method is called during login to check credentials. This hook fires
	 * just before the credentials are verified.
	 *
	 * Target class: authentication (global namespace)
	 * Target method: validate (instance method — but hook fires for static calls too)
	 *
	 * Note: on_method_enter hooks fire on the method entry point. For
	 * instance methods, this happens when the method is invoked on any instance.
	 *
	 * @param fusor_event $event
	 */
	#[on_method_enter(target: 'authentication::create_user_session')]
	public static function trace_authentication(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_04] Entering authentication::create_user_session');
		closelog();
	}

	/**
	 * Hook into the bridges copy operation.
	 *
	 * When someone clicks "Copy" on the bridges list page, the bridges::copy()
	 * method is called. This enter hook fires before the copy runs.
	 *
	 * Target: bridges::copy
	 */
	#[on_method_enter(target: 'bridges::copy')]
	public static function before_bridges_copy(fusor_event $event): void {
		$args = $event->arguments ?? [];
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_04] bridges::copy entered with ' . count($args) . ' argument(s)');
		closelog();
	}

	/**
	 * Hook with the raw array context signature.
	 *
	 * If you type-hint the parameter as `array`, you receive the raw
	 * context instead of a fusor_event object. Both work — choose whichever
	 * you prefer.
	 */
	#[on_method_enter(target: 'bridges::delete')]
	public static function before_bridges_delete(array $context): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_04] bridges::delete entered — target: ' . ($context['target'] ?? 'unknown'));
		closelog();
	}

	/**
	 * Hook with priority.
	 *
	 * Multiple enter hooks on the same target run in priority order
	 * (higher priority first).
	 */
	#[on_method_enter(target: 'bridges::toggle', priority: 50)]
	public static function before_bridges_toggle(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_04] bridges::toggle entered (priority 50)');
		closelog();
	}
}
