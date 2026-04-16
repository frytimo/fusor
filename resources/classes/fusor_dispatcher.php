<?php

namespace Frytimo\Fusor\resources\classes;

/**
 * Fusor dispatcher.
 */
class fusor_dispatcher {
	private static array $listeners = [];

	/**
	 * Register listener.
	 * @param mixed $event_name
	 * @param mixed $listener
	 * @param mixed $priority
	 * @return void
	 */
	public static function register_listener(string $event_name, callable $listener, int $priority = 0): void {
		$event_name = trim($event_name);
		if ($event_name === '') {
			return;
		}

		$listener_key = self::get_listener_key($listener);
		self::$listeners[$event_name][$priority][$listener_key] = $listener;
		krsort(self::$listeners[$event_name], SORT_NUMERIC);
	}

	/**
	 * Build a stable listener key so different classes sharing the same method
	 * name do not collide in listener storage.
	 * @param mixed $listener
	 * @return string
	 */
	private static function get_listener_key(callable $listener): string {
		if (is_string($listener)) {
			return 'fn:' . $listener;
		}

		if (is_array($listener)) {
			$target = $listener[0] ?? '';
			$method = (string) ($listener[1] ?? '');

			if (is_object($target)) {
				return 'obj:' . get_class($target) . '#' . spl_object_id($target) . '::' . $method;
			}

			return 'cls:' . ltrim((string) $target, '\\') . '::' . $method;
		}

		if ($listener instanceof \Closure) {
			return 'closure:' . spl_object_id($listener);
		}

		if (is_object($listener)) {
			return 'invokable:' . get_class($listener) . '#' . spl_object_id($listener);
		}

		return 'listener:' . md5(serialize($listener));
	}

	/**
	 * Clear listeners.
	 * @return void
	 */
	public static function clear_listeners(): void {
		self::$listeners = [];
	}

	/**
	 * Has listeners.
	 * @param mixed $event_name
	 * @return bool
	 */
	public static function has_listeners(string $event_name): bool {
		$event_name = trim($event_name);
		if ($event_name === '') {
			return false;
		}

		foreach (self::$listeners as $registered_event => $prioritized_listeners) {
			if (!self::event_matches($registered_event, $event_name)) {
				continue;
			}

			foreach ($prioritized_listeners as $listeners) {
				if (!empty($listeners)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine whether a registered event pattern matches a runtime event name.
	 * A trailing /* in the registered pattern also matches the base path itself.
	 * @param mixed $registered_event
	 * @param mixed $event_name
	 * @return bool
	 */
	private static function event_matches(string $registered_event, string $event_name): bool {
		if (fnmatch($registered_event, $event_name)) {
			return true;
		}

		// Support `/**/` as "zero or more directories" by also checking a
		// collapsed variant where the double-star segment is removed.
		if (strpos($registered_event, '/**/') !== false) {
			$collapsed_pattern = str_replace('/**/', '/', $registered_event);
			if (fnmatch($collapsed_pattern, $event_name)) {
				return true;
			}
		}

		if (!str_ends_with($registered_event, '/*')) {
			return false;
		}

		if (!self::is_directory_wildcard_base_match_enabled()) {
			return false;
		}

		$base_pattern = substr($registered_event, 0, -2);
		$base_pattern = $base_pattern !== '/' ? rtrim($base_pattern, '/') : '/';
		$normalized_event = $event_name !== '/' ? rtrim($event_name, '/') : '/';

		return $normalized_event === $base_pattern;
	}

	/**
	 * Returns whether `.../*` listeners should also match the base directory
	 * path with no trailing segment.
	 *
	 * Config key: [fusor_dispatcher] match_directory_on_wildcard=true|false
	 *
	 * @return bool
	 */
	private static function is_directory_wildcard_base_match_enabled(): bool {
		$value = $_ENV['fusor_dispatcher']['match_directory_on_wildcard'] ?? true;

		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value !== 0;
		}

		$parsed = filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($parsed === null) {
			return true;
		}

		return $parsed;
	}

	/**
	 * Register all static public methods using #[on(...)] discovered by fusor_discovery.
	 *
	 * @param \auto_loader $autoload
	 * @param bool         $force_refresh
	 *
	 * @return int Number of listeners registered
	 */
	public static function register_discovered_listeners(\auto_loader $autoload, bool $force_refresh = false): int {
		require_once dirname(__DIR__) . '/attributes/on.php';

		if ($force_refresh && method_exists($autoload, 'update')) {
			$autoload->update();
		}

		self::clear_listeners();

		if (!method_exists($autoload, 'get_attributes')) {
			return self::register_discovered_listeners_from_class_map($autoload);
		}

		fusor_discovery::discover_attributes($autoload, $force_refresh);

		$methods = fusor_discovery::get_methods();
		$registered = 0;
		$processed_methods = [];

		foreach ($methods as $method_entry) {
			$class_name = trim((string) ($method_entry['class'] ?? ''));
			$method_name = trim((string) ($method_entry['method'] ?? ''));

			if ($class_name === '' || $method_name === '') {
				continue;
			}

			$method_key = $class_name . '::' . $method_name;
			if (isset($processed_methods[$method_key])) {
				continue;
			}

			$processed_methods[$method_key] = true;

			if (!class_exists($class_name)) {
				continue;
			}

			try {
				$reflection_method = new \ReflectionMethod($class_name, $method_name);
			} catch (\ReflectionException $exception) {
				trigger_error('Fusor failed to reflect method ' . $method_key . ': ' . $exception->getMessage(), E_USER_WARNING);
				continue;
			}

			if (!$reflection_method->isPublic() || !$reflection_method->isStatic()) {
				continue;
			}

			$attributes = $reflection_method->getAttributes('frytimo\\fusor\\resources\\attributes\\on', \ReflectionAttribute::IS_INSTANCEOF);
			foreach ($attributes as $attribute) {
				try {
					$listener_attribute = $attribute->newInstance();
				} catch (\Throwable $exception) {
					trigger_error('Fusor failed to instantiate attribute on ' . $method_key . ': ' . $exception->getMessage(), E_USER_WARNING);
					continue;
				}

				$event_name = trim((string) ($listener_attribute->event_name ?? ''));
				if ($event_name === '') {
					continue;
				}

				$priority = (int) ($listener_attribute->priority ?? 0);
				self::register_listener($event_name, [$class_name, $method_name], $priority);
				++$registered;
			}
		}

		return $registered;
	}

	/**
	 * Fallback listener discovery for environments where auto_loader does not
	 * expose get_attributes().
	 *
	 * @param \auto_loader $autoload
	 *
	 * @return int
	 */
	private static function register_discovered_listeners_from_class_map(\auto_loader $autoload): int {
		if (!method_exists($autoload, 'get_class_list')) {
			return 0;
		}

		$class_list = $autoload->get_class_list();
		if (!is_array($class_list) || empty($class_list)) {
			return 0;
		}

		$registered = 0;
		foreach ($class_list as $class_name => $path) {
			if (!is_string($class_name) || trim($class_name) === '') {
				continue;
			}

			if (!is_string($path) || !is_file($path)) {
				continue;
			}

			$file_contents = @file_get_contents($path);
			if (!is_string($file_contents) || $file_contents === '') {
				continue;
			}

			$has_on_attribute = strpos($file_contents, 'frytimo\\fusor\\resources\\attributes\\on') !== false
				|| strpos($file_contents, '#[on(') !== false
				|| strpos($file_contents, '#[\\frytimo\\fusor\\resources\\attributes\\on') !== false;

			if (!$has_on_attribute) {
				continue;
			}

			if (!class_exists($class_name, false)) {
				try {
					@include_once $path;
				} catch (\Throwable $exception) {
					continue;
				}
			}

			if (!class_exists($class_name, false)) {
				continue;
			}

			try {
				$reflection_class = new \ReflectionClass($class_name);
			} catch (\ReflectionException $exception) {
				continue;
			}

			foreach ($reflection_class->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC) as $reflection_method) {
				$attributes = $reflection_method->getAttributes('frytimo\\fusor\\resources\\attributes\\on', \ReflectionAttribute::IS_INSTANCEOF);
				foreach ($attributes as $attribute) {
					try {
						$listener_attribute = $attribute->newInstance();
					} catch (\Throwable $exception) {
						trigger_error('Fusor failed to instantiate attribute on ' . $class_name . '::' . $reflection_method->getName() . ': ' . $exception->getMessage(), E_USER_WARNING);
						continue;
					}

					$event_name = trim((string) ($listener_attribute->event_name ?? ''));
					if ($event_name === '') {
						continue;
					}

					$priority = (int) ($listener_attribute->priority ?? 0);
					self::register_listener($event_name, [$class_name, $reflection_method->getName()], $priority);
					++$registered;
				}
			}
		}

		return $registered;
	}

	/**
	 * Dispatch.
	 * @param mixed $event
	 * @return void
	 */
	public static function dispatch(fusor_event $event): void {
		$event_name = $event->name;
		foreach (self::$listeners as $registered_event => $prioritized_listeners) {
			if (self::event_matches($registered_event, $event_name)) {
				foreach ($prioritized_listeners as $priority => $listeners) {
					foreach ($listeners as $listener) {
						try {
							call_user_func($listener, $event);
						} catch (\Throwable $exception) {
							trigger_error('Fusor listener failure for event ' . $event_name . ': ' . $exception->getMessage(), E_USER_WARNING);
						}
					}
				}
			}
		}
	}
}
