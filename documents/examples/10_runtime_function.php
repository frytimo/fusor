<?php

/**
 * Example 10: The #[runtime_function] Attribute — Add/Remove Functions
 *
 * The #[runtime_function] attribute uses uopz_add_function() to inject
 * new global functions or class methods at runtime, or uopz_del_function()
 * to remove them. Functions added this way persist across the FPM worker
 * lifetime (they survive between requests on the same worker).
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * ACTIONS:
 *   'add'    — Register a new global function or class method (aliases: 'load')
 *   'remove' — Remove an existing function or method (aliases: 'unload', 'delete')
 *
 * TARGET FORMAT:
 *   - Global function:  'my_function_name'
 *   - Class method:     'ClassName::my_method'
 *
 * PERSISTENCE:
 *   Unlike hooks (uopz_set_hook/uopz_set_return), functions added with
 *   uopz_add_function persist across requests on the same FPM worker.
 *   Fusor uses function_exists()/method_exists() guards to avoid double
 *   registration on worker reuse.
 *
 * @see app/fusor/resources/attributes/runtime_function.php
 * @see app/fusor/resources/classes/fusor_uopz.php (register_runtime_function_attributes)
 */

use Frytimo\Fusor\resources\attributes\runtime_function;

class example_10_runtime_function {

	/**
	 * Add a global helper function at runtime.
	 *
	 * After registration, this function is callable from anywhere:
	 *   $result = my_custom_helper('World');
	 *   // Returns: "Hello, World!"
	 *
	 * The method body becomes the function implementation.
	 */
	#[runtime_function(target: 'my_custom_helper', action: 'add')]
	public static function my_custom_helper(string $name = 'FusionPBX'): string {
		return 'Hello, ' . $name . '!';
	}

	/**
	 * Add a utility function for formatting phone numbers.
	 *
	 * Once registered, call it anywhere:
	 *   $formatted = format_phone_e164('5551234567', '1');
	 *   // Returns: "+15551234567"
	 */
	#[runtime_function(target: 'format_phone_e164', action: 'add')]
	public static function format_phone_e164(string $number, string $country_code = '1'): string {
		$digits = preg_replace('/\D/', '', $number) ?? '';
		if ($digits === '') {
			return '';
		}
		return '+' . $country_code . $digits;
	}

	/**
	 * Add a method to an existing class.
	 *
	 * This adds a new static method `bridges::get_summary()` that doesn't
	 * exist in the original bridges class. After registration:
	 *   $summary = bridges::get_summary();
	 *
	 * Note: The target uses '::' notation for class methods.
	 */
	#[runtime_function(target: 'bridges::get_summary', action: 'add')]
	public static function bridges_get_summary(): string {
		return 'Bridges summary generated at ' . date('c');
	}

	/**
	 * Remove a global function.
	 *
	 * Use action: 'remove' (or 'unload', 'delete') to remove a previously
	 * registered runtime function. The handler method body is not used
	 * for remove actions — it exists only as a placeholder.
	 *
	 * This is useful for cleaning up functions that were added by other
	 * modules or for disabling functionality at runtime.
	 */
	#[runtime_function(target: 'deprecated_old_helper', action: 'remove')]
	public static function remove_deprecated_helper(): void {
		// Handler body is not executed for 'remove' actions.
		// The attribute simply tells Fusor to call uopz_del_function('deprecated_old_helper').
	}

	/**
	 * Use the 'load' alias for 'add'.
	 *
	 * The following action aliases are supported:
	 *   'add' or 'load'     → uopz_add_function()
	 *   'remove', 'unload', 'delete' → uopz_del_function()
	 */
	#[runtime_function(target: 'my_debug_dump', action: 'load')]
	public static function my_debug_dump(mixed $value): string {
		return print_r($value, true);
	}
}
