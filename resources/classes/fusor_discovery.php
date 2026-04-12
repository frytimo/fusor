<?php

namespace frytimo\fusor\resources\classes;

use auto_loader;

class fusor_discovery {
	private const CACHE_KEY = 'fusor_discovery_registry';
	private const CACHE_FILE = 'fusor_discovery_cache.php';
	private const CACHE_VERSION = 5;

	/**
	 * Generic attribute discovery registry.
	 *
	 * @var array<string,mixed>
	 */
	private static array $registry = [
		'all' => [],
		'by_target_type' => [],
		'methods' => [],
	];

	public static function discover_attributes(auto_loader $auto_loader, bool $force_refresh = false): void {
		$source_mtime = self::get_source_mtime();
		if (!$force_refresh && self::load_cache($source_mtime)) {
			return;
		}

		self::$registry = [
			'all' => [],
			'by_target_type' => [],
			'methods' => [],
		];

		$attributes = $auto_loader->get_attributes();
		foreach ($attributes as $target_type => $targets) {
			if (!is_array($targets)) {
				continue;
			}

			foreach ($targets as $target_name => $entries) {
				if (!is_array($entries)) {
					continue;
				}

				foreach ($entries as $entry) {
					if (!is_array($entry)) {
						continue;
					}

					$normalized = self::normalize_entry((string) $target_type, (string) $target_name, $entry);
					self::$registry['all'][] = $normalized;
					self::$registry['by_target_type'][$target_type][] = $normalized;
					if ($target_type === 'method') {
						self::$registry['methods'][] = $normalized;
					}
				}
			}
		}

		self::store_cache($source_mtime);
	}

	/**
	 * Returns the complete attribute registry.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_registry(): array {
		return self::$registry;
	}

	/**
	 * Returns all discovered attributes, optionally filtered by attribute name.
	 *
	 * @param string $attribute_name
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_attributes(string $attribute_name = ''): array {
		$entries = self::$registry['all'] ?? [];
		if ($attribute_name === '') {
			return $entries;
		}

		$lookup_full = strtolower(trim($attribute_name, " \n\r\t\v\x00\\"));
		$lookup_short = strtolower(self::get_short_name($attribute_name));

		return array_values(array_filter($entries, static function (array $entry) use ($lookup_full, $lookup_short): bool {
			$entry_full = strtolower((string) ($entry['attribute'] ?? ''));
			$entry_short = strtolower((string) ($entry['attribute_short'] ?? ''));
			return $entry_full === $lookup_full || $entry_short === $lookup_short;
		}));
	}

	/**
	 * Returns entries for a specific target type.
	 *
	 * @param string $target_type
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_by_target_type(string $target_type): array {
		$target_type = trim($target_type);
		if ($target_type === '') {
			return [];
		}

		return self::$registry['by_target_type'][$target_type] ?? [];
	}

	/**
	 * Returns method entries (always includes class + method).
	 *
	 * @param string $attribute_name Optional filter by attribute name
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_methods(string $attribute_name = ''): array {
		$methods = self::$registry['methods'] ?? [];
		if ($attribute_name === '') {
			return $methods;
		}

		$lookup_full = strtolower(trim($attribute_name, " \n\r\t\v\x00\\"));
		$lookup_short = strtolower(self::get_short_name($attribute_name));

		return array_values(array_filter($methods, static function (array $entry) use ($lookup_full, $lookup_short): bool {
			$entry_full = strtolower((string) ($entry['attribute'] ?? ''));
			$entry_short = strtolower((string) ($entry['attribute_short'] ?? ''));
			return $entry_full === $lookup_full || $entry_short === $lookup_short;
		}));
	}

	/**
	 * Loads listeners from APCu/file cache when the source metadata timestamp matches.
	 *
	 * @param int $source_mtime
	 *
	 * @return bool
	 */
	private static function load_cache(int $source_mtime): bool {
		if (self::is_apcu_enabled()) {
			$cached_payload = apcu_fetch(self::CACHE_KEY, $exists);
			if ($exists && self::is_valid_cache_payload($cached_payload, $source_mtime)) {
				self::$registry = $cached_payload['registry'];
				return true;
			}
		}

		$file_path = self::get_cache_file_path();
		if (!file_exists($file_path)) {
			return false;
		}

		$cached_payload = include $file_path;
		if (!self::is_valid_cache_payload($cached_payload, $source_mtime)) {
			@unlink($file_path);
			return false;
		}

		self::$registry = $cached_payload['registry'];
		if (self::is_apcu_enabled()) {
			apcu_store(self::CACHE_KEY, $cached_payload);
		}

		return true;
	}

	/**
	 * Stores listeners to APCu when available and file cache as fallback.
	 *
	 * @param int $source_mtime
	 *
	 * @return void
	 */
	private static function store_cache(int $source_mtime): void {
		$payload = [
			'version' => self::CACHE_VERSION,
			'source_mtime' => $source_mtime,
			'registry' => self::$registry,
		];

		if (self::is_apcu_enabled()) {
			apcu_store(self::CACHE_KEY, $payload);
		}

		$file_path = self::get_cache_file_path();
		$file_data = var_export($payload, true);
		@file_put_contents($file_path, "<?php\n return " . $file_data . ";\n");
	}

	/**
	 * Clears the discovery cache from APCu and disk.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		if (self::is_apcu_enabled()) {
			apcu_delete(self::CACHE_KEY);
		}

		$file_path = self::get_cache_file_path();
		if (file_exists($file_path)) {
			@unlink($file_path);
		}
	}

	/**
	 * Returns the source metadata timestamp used to invalidate cache.
	 *
	 * @return int
	 */
	private static function get_source_mtime(): int {
		if (!defined('auto_loader::ATTRIBUTES_FILE')) {
			return 0;
		}

		$attributes_cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . auto_loader::ATTRIBUTES_FILE;
		if (!file_exists($attributes_cache_file)) {
			return 0;
		}

		$mtime = @filemtime($attributes_cache_file);
		return $mtime === false ? 0 : (int) $mtime;
	}

	/**
	 * Returns the path used for file cache.
	 *
	 * @return string
	 */
	private static function get_cache_file_path(): string {
		return sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::CACHE_FILE;
	}

	/**
	 * Validates discovery cache payload structure.
	 *
	 * @param mixed $payload
	 * @param int   $source_mtime
	 *
	 * @return bool
	 */
	private static function is_valid_cache_payload($payload, int $source_mtime): bool {
		return is_array($payload)
			&& ($payload['version'] ?? null) === self::CACHE_VERSION
			&& (int) ($payload['source_mtime'] ?? -1) === $source_mtime
			&& isset($payload['registry'])
			&& is_array($payload['registry']);
	}

	/**
	 * Indicates whether APCu is available and enabled.
	 *
	 * @return bool
	 */
	private static function is_apcu_enabled(): bool {
		return function_exists('apcu_enabled') && apcu_enabled();
	}

	/**
	 * Normalizes entries from auto_loader for consistent discovery lookups.
	 *
	 * @param string $target_type
	 * @param string $target_name
	 * @param array  $entry
	 *
	 * @return array<string,mixed>
	 */
	private static function normalize_entry(string $target_type, string $target_name, array $entry): array {
		$attribute_name = (string) ($entry['attribute'] ?? '');
		$class_name = (string) ($entry['class'] ?? '');
		$method_name = (string) ($entry['method'] ?? '');

		if ($target_type === 'method' && ($class_name === '' || $method_name === '') && strpos($target_name, '::') !== false) {
			$parts = explode('::', $target_name, 2);
			$class_name = $parts[0] ?? '';
			$method_name = $parts[1] ?? '';
		}

		$arguments_string = (string) ($entry['arguments'] ?? '');
		$parsed_arguments = self::parse_arguments($arguments_string);

		return [
			'attribute' => $attribute_name,
			'attribute_short' => self::get_short_name($attribute_name),
			'target_type' => $target_type,
			'target' => $target_name,
			'class' => $class_name,
			'method' => $method_name,
			'is_static' => (bool) ($entry['is_static'] ?? false),
			'visibility' => (string) ($entry['visibility'] ?? 'public'),
			'arguments' => $parsed_arguments,
			'arguments_raw' => $arguments_string,
			'raw' => (string) ($entry['raw'] ?? ''),
			'file' => (string) ($entry['file'] ?? ''),
			'line' => (int) ($entry['line'] ?? 0),
		];
	}

	/**
	 * Parses a string of named arguments into an associative array.
	 * Examples: "event_name: 'call.missed', priority: 100" => ['event_name' => 'call.missed', 'priority' => 100]
	 *
	 * @param string $arguments_string
	 *
	 * @return array<string,mixed>
	 */
	private static function parse_arguments(string $arguments_string): array {
		$arguments_string = trim($arguments_string);
		if ($arguments_string === '') {
			return [];
		}

		$result = [];
		$parts = self::split_arguments($arguments_string);

		foreach ($parts as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}

			// Try to match key: value pattern
			if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.+)$/s', $part, $match)) {
				$key = trim($match[1]);
				$value_str = trim($match[2]);
				$value = self::parse_value($value_str);
				$result[$key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Parses a single value from a string representation.
	 *
	 * @param string $value_str
	 *
	 * @return mixed
	 */
	private static function parse_value(string $value_str): mixed {
		$value_str = trim($value_str);

		// Boolean
		if ($value_str === 'true') {
			return true;
		}
		if ($value_str === 'false') {
			return false;
		}

		// Null
		if ($value_str === 'null') {
			return null;
		}

		// String with single quotes
		if (preg_match('/^\'(.*?)\'$/s', $value_str, $match)) {
			return stripcslashes($match[1]);
		}

		// String with double quotes
		if (preg_match('/^"(.*?)"$/s', $value_str, $match)) {
			return stripcslashes($match[1]);
		}

		// Array notation
		if (preg_match('/^\[(.*)\]$/s', $value_str, $match)) {
			$array_content = trim($match[1]);
			if ($array_content === '') {
				return [];
			}
			// Parse array elements
			$elements = self::split_arguments($array_content);
			$array_result = [];
			foreach ($elements as $element) {
				$element = trim($element);
				if ($element !== '') {
					// Try key => value or just value
					if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*|\d+)\s*=>\s*(.+)$/s', $element, $element_match)) {
						$array_result[$element_match[1]] = self::parse_value($element_match[2]);
					} else {
						$array_result[] = self::parse_value($element);
					}
				}
			}
			return $array_result;
		}

		// Integer
		if (preg_match('/^-?\d+$/', $value_str)) {
			return (int) $value_str;
		}

		// Float
		if (preg_match('/^-?\d+\.\d+$/', $value_str)) {
			return (float) $value_str;
		}

		// Fallback: return as string
		return $value_str;
	}

	/**
	 * Splits argument string by commas, respecting nested structures.
	 *
	 * @param string $arguments_string
	 *
	 * @return array<int,string>
	 */
	private static function split_arguments(string $arguments_string): array {
		$parts = [];
		$current = '';
		$depth = 0;
		$in_string = false;
		$string_char = '';

		for ($i = 0; $i < strlen($arguments_string); ++$i) {
			$char = $arguments_string[$i];

			// Handle string delimiters
			if (($char === '"' || $char === "'") && ($i === 0 || $arguments_string[$i - 1] !== '\\')) {
				if (!$in_string) {
					$in_string = true;
					$string_char = $char;
				} elseif ($char === $string_char) {
					$in_string = false;
				}
			}

			// Track depth (outside strings)
			if (!$in_string) {
				if ($char === '[' || $char === '(') {
					++$depth;
				} elseif ($char === ']' || $char === ')') {
					--$depth;
				}

				// Split on comma at depth 0
				if ($char === ',' && $depth === 0) {
					$parts[] = $current;
					$current = '';
					continue;
				}
			}

			$current .= $char;
		}

		if ($current !== '') {
			$parts[] = $current;
		}

		return $parts;
	}

	/**
	 * Returns the short (basename) form of a class-like name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private static function get_short_name(string $name): string {
		$parts = explode('\\', trim($name, '\\'));
		return end($parts) ?: '';
	}

}