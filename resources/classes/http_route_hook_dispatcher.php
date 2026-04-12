<?php

namespace frytimo\fusor\resources\classes;

class http_route_hook_dispatcher {
	private static bool $dispatched = false;

	public static function dispatch_request_hooks(\auto_loader $autoload, bool $force_refresh = false): int {
		if (self::$dispatched) {
			return 0;
		}

		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
		if (!in_array($method, ['GET', 'POST', 'PUT'], true)) {
			return 0;
		}

		$request_path = self::resolve_request_path();
		if ($request_path === '') {
			return 0;
		}

		require_once dirname(__DIR__) . '/attributes/route.php';
		require_once dirname(__DIR__) . '/attributes/get.php';
		require_once dirname(__DIR__) . '/attributes/post.php';
		require_once dirname(__DIR__) . '/attributes/put.php';
		require_once __DIR__ . '/fusor_discovery.php';
		require_once __DIR__ . '/fusor_event.php';

		$supports_attribute_discovery = method_exists($autoload, 'get_attributes');

		if ($supports_attribute_discovery && $force_refresh && method_exists($autoload, 'update')) {
			$autoload->update();
		}

		$attribute_name = strtolower($method);
		$methods = [];
		if ($supports_attribute_discovery) {
			fusor_discovery::discover_attributes($autoload, $force_refresh);
			$methods = fusor_discovery::get_methods($attribute_name);
		} else {
			$methods = self::discover_methods_by_reflection($attribute_name);
		}
		$invoked = 0;
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

			$route_attributes = self::get_route_attributes($reflection_method, $method);
			if (empty($route_attributes)) {
				continue;
			}

			foreach ($route_attributes as $route_attribute) {
				$route_path = self::normalize_path((string) ($route_attribute->path ?? ''));
				$route_method = strtoupper((string) ($route_attribute->method ?? ''));

				if ($route_path === '' || $route_method !== $method) {
					continue;
				}

				$route_params = [];
				if (!self::path_matches($route_path, $request_path, $route_params)) {
					continue;
				}

				$event = new fusor_event('http_' . strtolower($method), data: [
					'method' => $method,
					'route' => $route_path,
					'path' => $request_path,
					'params' => $route_params,
					'query' => $_GET,
					'body' => $_POST,
				]);

				try {
					if ($reflection_method->getNumberOfParameters() > 0) {
						$reflection_method->invoke(null, $event);
					} else {
						$reflection_method->invoke(null);
					}
					++$invoked;
				} catch (\Throwable $exception) {
					trigger_error('Fusor request hook failure for ' . $method_key . ': ' . $exception->getMessage(), E_USER_WARNING);
				}
			}
		}

		self::$dispatched = true;
		return $invoked;
	}

	private static function discover_methods_by_reflection(string $attribute_name): array {
		$attribute_class = 'frytimo\\fusor\\resources\\attributes\\' . strtolower($attribute_name);
		$methods = [];

		foreach (get_declared_classes() as $class_name) {
			try {
				$reflection_class = new \ReflectionClass($class_name);
			} catch (\ReflectionException $exception) {
				continue;
			}

			foreach ($reflection_class->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflection_method) {
				if (!$reflection_method->isStatic()) {
					continue;
				}

				$attributes = $reflection_method->getAttributes($attribute_class, \ReflectionAttribute::IS_INSTANCEOF);
				if (empty($attributes)) {
					continue;
				}

				$methods[] = [
					'class' => $class_name,
					'method' => $reflection_method->getName(),
				];
			}
		}

		return $methods;
	}

	private static function get_route_attributes(\ReflectionMethod $reflection_method, string $method): array {
		$attribute_class = 'frytimo\\fusor\\resources\\attributes\\' . strtolower($method);

		$attributes = $reflection_method->getAttributes($attribute_class, \ReflectionAttribute::IS_INSTANCEOF);
		$instances = [];

		foreach ($attributes as $attribute) {
			try {
				$instances[] = $attribute->newInstance();
			} catch (\Throwable $exception) {
				trigger_error('Fusor failed to instantiate route attribute: ' . $exception->getMessage(), E_USER_WARNING);
			}
		}

		return $instances;
	}

	private static function resolve_request_path(): string {
		$request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		if ($request_uri !== '') {
			$parsed = parse_url($request_uri, PHP_URL_PATH);
			if (is_string($parsed)) {
				return self::normalize_path($parsed);
			}
		}

		$script_name = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
		return self::normalize_path($script_name);
	}

	private static function normalize_path(string $path): string {
		$path = trim($path);
		if ($path === '') {
			return '';
		}

		$path = '/' . ltrim($path, '/');
		$path = preg_replace('#/+#', '/', $path);

		if ($path !== '/' && str_ends_with($path, '/')) {
			$path = rtrim($path, '/');
		}

		return $path;
	}

	private static function path_matches(string $route_path, string $request_path, array &$route_params): bool {
		if ($route_path === $request_path) {
			$route_params = [];
			return true;
		}

		if (strpos($route_path, '*') !== false && fnmatch($route_path, $request_path)) {
			$route_params = [];
			return true;
		}

		if (strpos($route_path, '{') === false) {
			return false;
		}

		$route_parts = explode('/', trim($route_path, '/'));
		$request_parts = explode('/', trim($request_path, '/'));
		if (count($route_parts) !== count($request_parts)) {
			return false;
		}

		$param_names = [];
		$regex_segments = [];
		foreach ($route_parts as $segment) {
			if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $match) === 1) {
				$param_names[] = $match[1];
				$regex_segments[] = '([^/]+)';
				continue;
			}

			$regex_segments[] = preg_quote($segment, '#');
		}

		$regex = '#^/' . implode('/', $regex_segments) . '$#';
		$matched = preg_match($regex, $request_path, $matches);
		if ($matched !== 1) {
			return false;
		}

		array_shift($matches);
		$route_params = [];
		foreach ($param_names as $index => $name) {
			$route_params[$name] = $matches[$index] ?? null;
		}

		return true;
	}
}
