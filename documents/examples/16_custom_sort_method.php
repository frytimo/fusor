<?php

/**
 * Example 16: Custom Sort Method via runtime_function
 *
 * This example uses #[runtime_function] to inject a custom sort function
 * that can be used by any FusionPBX page or app. Runtime functions are
 * globally available once registered and persist for the FPM worker lifetime.
 *
 * USE CASE:
 *   FusionPBX displays lists of extensions, bridges, gateways, etc. You
 *   may want to sort these differently than the default. By injecting a
 *   custom sort function at runtime, any code can call it without
 *   modifications to core files.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+ and uopz extension
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * USAGE AFTER INSTALLATION:
 *   // Sort an array of records by extension number
 *   $records = [...];
 *   usort($records, fusor_sort_by_field('extension'));
 *
 *   // Sort by description, descending
 *   usort($records, fusor_sort_by_field('description', 'desc'));
 *
 *   // Sort bridges by name using natural ordering
 *   $bridges = [...];
 *   usort($bridges, fusor_natural_sort_by('bridge_name'));
 *
 * @see app/fusor/resources/attributes/runtime_function.php
 * @see app/fusor/resources/classes/fusor_uopz.php
 */

use Frytimo\Fusor\resources\attributes\runtime_function;

class example_16_custom_sort_method {

	/**
	 * Inject a generic field-based sort comparator factory.
	 *
	 * Returns a closure usable with usort() that compares array elements
	 * by a specified field name, with optional ascending/descending order.
	 *
	 * After registration, call it as a global function:
	 *   usort($items, fusor_sort_by_field('name'));
	 *   usort($items, fusor_sort_by_field('priority', 'desc'));
	 *
	 * @param string $field_name The array key to sort by
	 * @param string $direction  'asc' or 'desc'
	 * @return callable A comparison function for usort()
	 */
	#[runtime_function(target: 'fusor_sort_by_field', action: 'add')]
	public static function fusor_sort_by_field(string $field_name, string $direction = 'asc'): callable {
		$descending = strtolower($direction) === 'desc';

		return static function ($a, $b) use ($field_name, $descending): int {
			$val_a = is_array($a) ? ($a[$field_name] ?? '') : '';
			$val_b = is_array($b) ? ($b[$field_name] ?? '') : '';

			$result = strcmp((string) $val_a, (string) $val_b);
			return $descending ? -$result : $result;
		};
	}

	/**
	 * Inject a natural sort comparator (handles numeric-embedded strings).
	 *
	 * Natural sorting makes "ext10" come after "ext9" instead of after "ext1".
	 *
	 * Usage:
	 *   usort($records, fusor_natural_sort_by('extension'));
	 */
	#[runtime_function(target: 'fusor_natural_sort_by', action: 'add')]
	public static function fusor_natural_sort_by(string $field_name, string $direction = 'asc'): callable {
		$descending = strtolower($direction) === 'desc';

		return static function ($a, $b) use ($field_name, $descending): int {
			$val_a = is_array($a) ? ($a[$field_name] ?? '') : '';
			$val_b = is_array($b) ? ($b[$field_name] ?? '') : '';

			$result = strnatcasecmp((string) $val_a, (string) $val_b);
			return $descending ? -$result : $result;
		};
	}

	/**
	 * Inject a multi-field sort comparator.
	 *
	 * Sort by multiple fields, each with its own direction.
	 *
	 * Usage:
	 *   usort($records, fusor_sort_multi([
	 *       ['field' => 'domain_name', 'direction' => 'asc'],
	 *       ['field' => 'extension',   'direction' => 'asc'],
	 *   ]));
	 */
	#[runtime_function(target: 'fusor_sort_multi', action: 'add')]
	public static function fusor_sort_multi(array $sort_fields): callable {
		return static function ($a, $b) use ($sort_fields): int {
			foreach ($sort_fields as $field_spec) {
				$field = $field_spec['field'] ?? '';
				$desc = strtolower($field_spec['direction'] ?? 'asc') === 'desc';

				$val_a = is_array($a) ? ($a[$field] ?? '') : '';
				$val_b = is_array($b) ? ($b[$field] ?? '') : '';

				$result = strnatcasecmp((string) $val_a, (string) $val_b);
				if ($desc) {
					$result = -$result;
				}

				if ($result !== 0) {
					return $result;
				}
			}

			return 0;
		};
	}
}
