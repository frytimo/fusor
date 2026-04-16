<?php

namespace Frytimo\Fusor\resources\attributes;

use Attribute;

/**
 * Add or remove runtime functions or methods when uopz is available.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class runtime_function extends on {
	public readonly string $target;
	public readonly string $action;
	public readonly ?string $class_name;
	public readonly string $function_name;
	public readonly bool $is_method;

	/**
	 * Construct.
	 */
	public function __construct(string $target, string $action = 'add', int $priority = 0) {
		$target = trim($target, " \n\r\t\v\x00\\");
		if ($target === '') {
			throw new \InvalidArgumentException('runtime function target must not be empty');
		}

		$action = self::normalize_action($action);
		parent::__construct(event_name: 'runtime_function_' . $action, priority: $priority);

		$this->target = $target;
		$this->action = $action;

		if (strpos($target, '::') !== false) {
			[$class_name, $function_name] = explode('::', $target, 2);
			$class_name = trim($class_name, " \n\r\t\v\x00\\");
			$function_name = trim($function_name);
			if ($class_name === '' || $function_name === '') {
				throw new \InvalidArgumentException('runtime method target must be in the form ClassName::methodName');
			}

			$this->is_method = true;
			$this->class_name = $class_name;
			$this->function_name = $function_name;
		} else {
			$this->is_method = false;
			$this->class_name = null;
			$this->function_name = $target;
		}
	}

	/**
	 * Normalize action.
	 */
	public static function normalize_action(string $action): string {
		$action = strtolower(trim($action));

		return match ($action) {
			'load' => 'add',
			'unload', 'remove', 'delete' => 'remove',
			'add' => 'add',
			default => throw new \InvalidArgumentException('Invalid runtime function action. Valid names: add, load, remove, unload, delete.'),
		};
	}
}
