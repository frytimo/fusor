<?php

/**
 * Example 11: The #[override_constant] Attribute — Change Constants
 *
 * The #[override_constant] attribute uses uopz_redefine() to change the
 * value of global constants or class constants at runtime.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * TARGET FORMAT:
 *   - Global constant:  'CONSTANT_NAME'
 *   - Class constant:   'ClassName::CONSTANT_NAME'
 *
 * VALUE:
 *   The value parameter in the attribute sets the new constant value.
 *   The handler method must also return the same value (this is the
 *   implementation contract with fusor_uopz).
 *
 * IMPORTANT: Constant overrides affect the entire request lifecycle.
 * Use them sparingly and document any overrides thoroughly.
 *
 * @see app/fusor/resources/attributes/override_constant.php
 * @see app/fusor/resources/classes/fusor_uopz.php
 */

use Frytimo\Fusor\resources\attributes\override_constant;

class example_11_override_constant {

	/**
	 * Override a class constant.
	 *
	 * Change the app_name constant on the bridges class. This affects
	 * all code that reads bridges::app_name during the request.
	 *
	 * Original: bridges::app_name = 'bridges'
	 * Override: bridges::app_name = 'custom_bridges'
	 */
	#[override_constant(target: 'bridges::app_name', value: 'custom_bridges')]
	public static function override_bridges_app_name(): string {
		return 'custom_bridges';
	}

	/**
	 * Override a numeric constant.
	 *
	 * Change a timeout or limit constant to a custom value.
	 */
	#[override_constant(target: 'event_guard::FLOOD_THRESHOLD', value: 100)]
	public static function override_flood_threshold(): int {
		return 100;
	}

	/**
	 * Override a boolean constant.
	 */
	#[override_constant(target: 'bridges::ENABLE_LOGGING', value: true)]
	public static function override_enable_logging(): bool {
		return true;
	}

	/**
	 * Override with priority control.
	 *
	 * When multiple override_constant attributes target the same constant,
	 * priority determines the order. The last one to run wins.
	 */
	#[override_constant(target: 'bridges::app_uuid', value: '00000000-0000-0000-0000-000000000000', priority: 10)]
	public static function override_bridges_app_uuid(): string {
		return '00000000-0000-0000-0000-000000000000';
	}
}
