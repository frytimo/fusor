<?php

namespace fusor\resources\classes;

class fusor_dispatcher {
	private static array $listeners = [];

	public static function register_listener(string $event_name, callable $listener, int $priority = 0): void {
		$event_name = trim($event_name);
		if ($event_name === '') {
			return;
		}

		self::$listeners[$event_name][$priority][] = $listener;
		krsort(self::$listeners[$event_name], SORT_NUMERIC);
	}

	public static function clear_listeners(): void {
		self::$listeners = [];
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

		fusor_discovery::discover_attributes($autoload, $force_refresh);
		self::clear_listeners();

		$methods = fusor_discovery::get_methods('on');
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

			$attributes = $reflection_method->getAttributes('fusor\\resources\\attributes\\on', \ReflectionAttribute::IS_INSTANCEOF);
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

	public static function dispatch(fusor_event $event): void {
		$event_name = $event->name;
		foreach (self::$listeners as $registered_event => $prioritized_listeners) {
			if (fnmatch($registered_event, $event_name)) {
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