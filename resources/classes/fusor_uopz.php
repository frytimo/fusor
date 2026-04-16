<?php

declare(strict_types=1);

namespace Frytimo\Fusor\resources\classes;

use Frytimo\Fusor\resources\attributes\on_method;
use Frytimo\Fusor\resources\attributes\override_constant;
use Frytimo\Fusor\resources\attributes\runtime_function;

/**
 * Optional uopz integration for Fusor attribute auto-wiring.
 */
class fusor_uopz {
	private const SYSLOG_IDENT = 'FusionPBX';

	/** @var array<string, bool> */
	private static array $installed = [];

	/** @var array<string, bool> */
	private static array $logged = [];

	/**
	 * Discover and register all uopz-backed attributes.
	 */
	public static function register_discovered_hooks(\auto_loader $autoload, bool $force_refresh = false): int {
		require_once dirname(__DIR__) . '/attributes/on.php';
		require_once dirname(__DIR__) . '/attributes/on_method.php';
		require_once dirname(__DIR__) . '/attributes/override_constant.php';
		require_once dirname(__DIR__) . '/attributes/runtime_function.php';

		if ($force_refresh && method_exists($autoload, 'update')) {
			$autoload->update();
		}

		fusor_discovery::discover_attributes($autoload, $force_refresh);

		$registered = 0;
		$registered += self::register_on_method_attributes();
		$registered += self::register_override_constant_attributes();
		$registered += self::register_runtime_function_attributes();

		return $registered;
	}

	/**
	 * Register entry and wrapper hooks.
	 */
	private static function register_on_method_attributes(): int {
		$registered = 0;

		foreach (self::get_handler_reflections() as $handler) {
			foreach ($handler['reflection']->getAttributes(on_method::class, \ReflectionAttribute::IS_INSTANCEOF) as $reflection_attribute) {
				try {
					$attribute = $reflection_attribute->newInstance();
				} catch (\Throwable $exception) {
					self::log_once('attr:on_method:' . ($handler['name'] ?? 'unknown'), 'Failed to instantiate on_method attribute for ' . ($handler['name'] ?? 'unknown') . ': ' . $exception->getMessage());
					continue;
				}

				if (!self::ensure_extension_available('on_method', $attribute->target)) {
					continue;
				}

				if ($attribute->phase === 'enter') {
					if (self::install_enter_hook($attribute, $handler['callable'])) {
						$registered++;
					}
					continue;
				}

				if (self::install_return_wrapper($attribute, $handler['callable'])) {
					$registered++;
				}
			}
		}

		return $registered;
	}

	/**
	 * Apply constant overrides.
	 */
	private static function register_override_constant_attributes(): int {
		$registered = 0;

		foreach (self::get_handler_reflections() as $handler) {
			foreach ($handler['reflection']->getAttributes(override_constant::class, \ReflectionAttribute::IS_INSTANCEOF) as $reflection_attribute) {
				try {
					$attribute = $reflection_attribute->newInstance();
				} catch (\Throwable $exception) {
					self::log_once('attr:override_constant:' . ($handler['name'] ?? 'unknown'), 'Failed to instantiate override_constant attribute for ' . ($handler['name'] ?? 'unknown') . ': ' . $exception->getMessage());
					continue;
				}

				if (!self::ensure_extension_available('override_constant', $attribute->target, ['uopz_redefine'])) {
					continue;
				}

				$registration_key = 'constant:' . $attribute->target;
				if (isset(self::$installed[$registration_key])) {
					continue;
				}

				$value = $attribute->value;
				if ($value === null) {
					$value = self::invoke_handler($handler['callable'], [
						'phase' => 'constant_override',
						'target' => $attribute->target,
					]);
				}

				try {
					$result = $attribute->is_class_constant
						? self::call_uopz('uopz_redefine', $attribute->class_name, $attribute->constant_name, $value)
						: self::call_uopz('uopz_redefine', $attribute->constant_name, $value);
				} catch (\Throwable $exception) {
					self::log_once($registration_key . ':exception', 'Failed to redefine constant ' . $attribute->target . ': ' . $exception->getMessage());
					continue;
				}

				if ($result === false) {
					self::log_once($registration_key . ':failed', 'uopz_redefine returned false for constant ' . $attribute->target . '.');
					continue;
				}

				self::$installed[$registration_key] = true;
				$registered++;
			}
		}

		return $registered;
	}

	/**
	 * Add or remove runtime functions.
	 */
	private static function register_runtime_function_attributes(): int {
		$registered = 0;

		foreach (self::get_handler_reflections() as $handler) {
			foreach ($handler['reflection']->getAttributes(runtime_function::class, \ReflectionAttribute::IS_INSTANCEOF) as $reflection_attribute) {
				try {
					$attribute = $reflection_attribute->newInstance();
				} catch (\Throwable $exception) {
					self::log_once('attr:runtime_function:' . ($handler['name'] ?? 'unknown'), 'Failed to instantiate runtime_function attribute for ' . ($handler['name'] ?? 'unknown') . ': ' . $exception->getMessage());
					continue;
				}

				if (!self::ensure_extension_available('runtime_function', $attribute->target, ['uopz_add_function', 'uopz_del_function'])) {
					continue;
				}

				$registration_key = 'runtime_function:' . $attribute->action . ':' . $attribute->target;
				if (isset(self::$installed[$registration_key])) {
					continue;
				}

				try {
					if ($attribute->action === 'add') {
						$closure = \Closure::fromCallable($handler['callable']);
						$result = $attribute->is_method
							? self::call_uopz('uopz_add_function', $attribute->class_name, $attribute->function_name, $closure)
							: self::call_uopz('uopz_add_function', $attribute->function_name, $closure);
					} else {
						$result = $attribute->is_method
							? self::call_uopz('uopz_del_function', $attribute->class_name, $attribute->function_name)
							: self::call_uopz('uopz_del_function', $attribute->function_name);
					}
				} catch (\Throwable $exception) {
					self::log_once($registration_key . ':exception', 'Failed to ' . $attribute->action . ' runtime function ' . $attribute->target . ': ' . $exception->getMessage());
					continue;
				}

				if ($result === false) {
					self::log_once($registration_key . ':failed', 'uopz ' . $attribute->action . ' returned false for runtime function ' . $attribute->target . '.');
					continue;
				}

				self::$installed[$registration_key] = true;
				$registered++;
			}
		}

		return $registered;
	}

	/**
	 * Install an enter hook.
	 */
	private static function install_enter_hook(on_method $attribute, callable $handler): bool {
		$target = self::resolve_target($attribute->target);
		if ($target === null) {
			return false;
		}

		$registration_key = 'hook:enter:' . $attribute->target . ':' . self::callable_key($handler);
		if (isset(self::$installed[$registration_key])) {
			return false;
		}

		$manager_class = self::class;
		$hook = function (...$arguments) use ($manager_class, $attribute, $handler, $target): void {
			$context = [
				'phase' => $attribute->phase,
				'target' => $attribute->target,
				'class' => $target['class_name'],
				'function' => $target['function_name'],
				'is_method' => $target['is_method'],
				'arguments' => $arguments,
				'result' => null,
			];

			$manager_class::invoke_handler($handler, $context);
		};

		try {
			$result = $target['is_method']
				? self::call_uopz('uopz_set_hook', $target['class_name'], $target['function_name'], $hook)
				: self::call_uopz('uopz_set_hook', $target['function_name'], $hook);
		} catch (\Throwable $exception) {
			self::log_once($registration_key . ':exception', 'Failed to install enter hook for ' . $attribute->target . ': ' . $exception->getMessage());
			return false;
		}

		if ($result === false) {
			self::log_once($registration_key . ':failed', 'uopz_set_hook returned false for ' . $attribute->target . '.');
			return false;
		}

		self::$installed[$registration_key] = true;
		return true;
	}

	/**
	 * Install a return wrapper for exit/around/replace behavior.
	 */
	private static function install_return_wrapper(on_method $attribute, callable $handler): bool {
		$target = self::resolve_target($attribute->target);
		if ($target === null) {
			return false;
		}

		if ($target['is_method'] && !$target['is_static']) {
			self::log_once('wrapper:non_static:' . $attribute->target, 'Skipping ' . $attribute->phase . ' hook for ' . $attribute->target . ' because non-static method wrappers are not supported in the first pass. Use enter/before instead.');
			return false;
		}

		$registration_key = 'hook:' . $attribute->phase . ':' . $attribute->target . ':' . self::callable_key($handler);
		if (isset(self::$installed[$registration_key])) {
			return false;
		}

		$manager_class = self::class;
		$wrapper = null;
		$wrapper = function (...$arguments) use (&$wrapper, $manager_class, $attribute, $handler, $target): mixed {
			$manager_class::unset_return_wrapper($target);
			try {
				$result = $manager_class::invoke_original_target($target, $arguments);
			} finally {
				if ($wrapper instanceof \Closure) {
					$manager_class::restore_return_wrapper($target, $wrapper);
				}
			}

			$context = [
				'phase' => $attribute->phase,
				'target' => $attribute->target,
				'class' => $target['class_name'],
				'function' => $target['function_name'],
				'is_method' => $target['is_method'],
				'arguments' => $arguments,
				'result' => $result,
			];

			$replacement = $manager_class::invoke_handler($handler, $context);
			return $replacement !== null ? $replacement : $result;
		};

		try {
			$result = $target['is_method']
				? self::call_uopz('uopz_set_return', $target['class_name'], $target['function_name'], $wrapper, true)
				: self::call_uopz('uopz_set_return', $target['function_name'], $wrapper, true);
		} catch (\Throwable $exception) {
			self::log_once($registration_key . ':exception', 'Failed to install ' . $attribute->phase . ' wrapper for ' . $attribute->target . ': ' . $exception->getMessage());
			return false;
		}

		if ($result === false) {
			self::log_once($registration_key . ':failed', 'uopz_set_return returned false for ' . $attribute->target . '.');
			return false;
		}

		self::$installed[$registration_key] = true;
		return true;
	}

	/**
	 * Resolve a target function or method for uopz use.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function resolve_target(string $target): ?array {
		$target = trim($target, " \n\r\t\v\x00\\");
		if ($target === '') {
			return null;
		}

		if (strpos($target, '::') !== false) {
			[$class_name, $function_name] = explode('::', $target, 2);
			$class_name = trim($class_name, " \n\r\t\v\x00\\");
			$function_name = trim($function_name);

			if ($class_name === '' || $function_name === '') {
				self::log_once('target:' . $target, 'Invalid uopz method target ' . $target . '.');
				return null;
			}

			if (!class_exists($class_name)) {
				self::log_once('target:' . $target, 'Unable to auto-wire uopz target because class ' . $class_name . ' is not available for ' . $target . '.');
				return null;
			}

			if (!method_exists($class_name, $function_name)) {
				self::log_once('target:' . $target, 'Unable to auto-wire uopz target because method ' . $target . ' does not exist.');
				return null;
			}

			$reflection = new \ReflectionMethod($class_name, $function_name);
			return [
				'is_method' => true,
				'class_name' => $class_name,
				'function_name' => $function_name,
				'is_static' => $reflection->isStatic(),
			];
		}

		if (!function_exists($target)) {
			self::log_once('target:' . $target, 'Unable to auto-wire uopz target because function ' . $target . ' is not available.');
			return null;
		}

		return [
			'is_method' => false,
			'class_name' => null,
			'function_name' => $target,
			'is_static' => true,
		];
	}

	/**
	 * Build callables from discovered methods and functions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_handler_reflections(): array {
		$result = [];
		$processed = [];
		$entries = array_merge(
			fusor_discovery::get_by_target_type('method'),
			fusor_discovery::get_by_target_type('function')
		);

		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$attribute_name = strtolower((string) ($entry['attribute_short'] ?? $entry['attribute'] ?? ''));
			if (!in_array($attribute_name, ['on_method', 'override_constant', 'runtime_function'], true)) {
				continue;
			}

			$target_type = (string) ($entry['target_type'] ?? '');
			$file = (string) ($entry['file'] ?? '');
			if ($file !== '' && file_exists($file)) {
				require_once $file;
			}

			if ($target_type === 'method') {
				$class_name = trim((string) ($entry['class'] ?? ''));
				$method_name = trim((string) ($entry['method'] ?? ''));
				if ($class_name === '' || $method_name === '' || isset($processed['method:' . $class_name . '::' . $method_name])) {
					continue;
				}

				$processed['method:' . $class_name . '::' . $method_name] = true;
				if (!class_exists($class_name)) {
					continue;
				}

				try {
					$reflection = new \ReflectionMethod($class_name, $method_name);
				} catch (\ReflectionException $exception) {
					self::log_once('handler:method:' . $class_name . '::' . $method_name, 'Unable to reflect uopz handler ' . $class_name . '::' . $method_name . ': ' . $exception->getMessage(), LOG_WARNING);
					continue;
				}

				if (!$reflection->isPublic() || !$reflection->isStatic()) {
					self::log_once('handler:method:' . $class_name . '::' . $method_name, 'Skipping uopz handler ' . $class_name . '::' . $method_name . ' because it must be public static.', LOG_WARNING);
					continue;
				}

				$result[] = [
					'name' => $class_name . '::' . $method_name,
					'callable' => [$class_name, $method_name],
					'reflection' => $reflection,
				];
				continue;
			}

			$function_name = trim((string) ($entry['target'] ?? ''));
			if ($function_name === '' || isset($processed['function:' . $function_name])) {
				continue;
			}

			$processed['function:' . $function_name] = true;
			if (!function_exists($function_name)) {
				continue;
			}

			try {
				$reflection = new \ReflectionFunction($function_name);
			} catch (\ReflectionException $exception) {
				self::log_once('handler:function:' . $function_name, 'Unable to reflect uopz handler function ' . $function_name . ': ' . $exception->getMessage(), LOG_WARNING);
				continue;
			}

			$result[] = [
				'name' => $function_name,
				'callable' => $function_name,
				'reflection' => $reflection,
			];
		}

		return $result;
	}

	/**
	 * Invoke a handler with a flexible signature.
	 */
	public static function invoke_handler(callable $handler, array $context): mixed {
		try {
			$reflection = is_array($handler)
				? new \ReflectionMethod((string) $handler[0], (string) $handler[1])
				: new \ReflectionFunction($handler);

			return $reflection->getNumberOfParameters() === 0
				? call_user_func($handler)
				: call_user_func($handler, $context);
		} catch (\Throwable $exception) {
			self::log_once('handler_failure:' . self::callable_key($handler), 'uopz handler ' . self::callable_key($handler) . ' failed: ' . $exception->getMessage());
			return $context['result'] ?? null;
		}
	}

	/**
	 * Call the original target while the wrapper is temporarily disabled.
	 */
	public static function invoke_original_target(array $target, array $arguments): mixed {
		if ($target['is_method']) {
			return forward_static_call_array([$target['class_name'], $target['function_name']], $arguments);
		}

		return call_user_func_array($target['function_name'], $arguments);
	}

	/**
	 * Remove a previously set return wrapper.
	 */
	public static function unset_return_wrapper(array $target): void {
		try {
			if ($target['is_method']) {
				self::call_uopz('uopz_unset_return', $target['class_name'], $target['function_name']);
			} else {
				self::call_uopz('uopz_unset_return', $target['function_name']);
			}
		} catch (\Throwable $exception) {
			self::log_once('unset_return:' . ($target['class_name'] ?? '') . '::' . $target['function_name'], 'Failed to unset uopz return wrapper for ' . ($target['class_name'] ? $target['class_name'] . '::' : '') . $target['function_name'] . ': ' . $exception->getMessage());
		}
	}

	/**
	 * Restore a return wrapper after the original call has finished.
	 */
	public static function restore_return_wrapper(array $target, \Closure $wrapper): void {
		try {
			if ($target['is_method']) {
				self::call_uopz('uopz_set_return', $target['class_name'], $target['function_name'], $wrapper, true);
			} else {
				self::call_uopz('uopz_set_return', $target['function_name'], $wrapper, true);
			}
		} catch (\Throwable $exception) {
			self::log_once('restore_return:' . ($target['class_name'] ?? '') . '::' . $target['function_name'], 'Failed to restore uopz return wrapper for ' . ($target['class_name'] ? $target['class_name'] . '::' : '') . $target['function_name'] . ': ' . $exception->getMessage());
		}
	}

	/**
	 * Ensure the uopz extension and required functions are available.
	 */
	private static function ensure_extension_available(string $feature, string $target, array $functions = ['uopz_set_hook', 'uopz_set_return', 'uopz_unset_return']): bool {
		if (!extension_loaded('uopz')) {
			self::log_once('missing_extension:' . $feature, 'uopz extension is not loaded; skipping auto-wiring for ' . $feature . ' on ' . $target . '.');
			return false;
		}

		foreach ($functions as $function_name) {
			if (!function_exists($function_name)) {
				self::log_once('missing_function:' . $function_name, 'uopz extension appears incomplete or disabled because ' . $function_name . ' is unavailable; skipping auto-wiring for ' . $target . '.');
				return false;
			}
		}

		return true;
	}

	/**
	 * Build a stable callable key.
	 */
	private static function callable_key(callable $callable): string {
		if (is_string($callable)) {
			return $callable;
		}

		if (is_array($callable)) {
			return (is_object($callable[0]) ? get_class($callable[0]) : (string) $callable[0]) . '::' . (string) ($callable[1] ?? '');
		}

		if ($callable instanceof \Closure) {
			return 'closure:' . spl_object_id($callable);
		}

		return 'callable:' . md5(serialize($callable));
	}

	/**
	 * Call a uopz function dynamically to stay compatible with optional installs.
	 */
	private static function call_uopz(string $function_name, mixed ...$arguments): mixed {
		if (!function_exists($function_name)) {
			throw new \RuntimeException('Missing required uopz function ' . $function_name . '.');
		}

		return call_user_func_array('\\' . $function_name, $arguments);
	}

	/**
	 * Log to syslog once per request.
	 */
	private static function log_once(string $key, string $message, int $level = LOG_ERR): void {
		if (isset(self::$logged[$key])) {
			return;
		}

		self::$logged[$key] = true;
		openlog(self::SYSLOG_IDENT, LOG_NDELAY | LOG_PID, LOG_USER);
		syslog($level, '[fusor_uopz] ' . $message);
		closelog();
	}
}
