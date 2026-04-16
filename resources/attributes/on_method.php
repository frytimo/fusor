<?php

namespace Frytimo\Fusor\resources\attributes;

use Attribute;

/**
 * UOPZ-backed hook registration attribute for functions and methods.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class on_method extends on {
	public readonly string $target;
	public readonly string $phase;
	public readonly ?string $class_name;
	public readonly string $function_name;
	public readonly bool $is_method;

	/**
	 * Construct.
	 *
	 * Valid phase names: enter, exit, before, after, around, replace.
	 */
	public function __construct(string $target, string $event_name = 'enter', int $priority = 0) {
		$target = trim($target, " \n\r\t\v\x00\\");
		if ($target === '') {
			throw new \InvalidArgumentException('uopz target must not be empty');
		}

		$phase = self::normalize_phase($event_name);
		parent::__construct(event_name: $phase, priority: $priority);

		$this->target = $target;
		$this->phase = $phase;

		if (strpos($target, '::') !== false) {
			[$class_name, $function_name] = explode('::', $target, 2);
			$class_name = trim($class_name, " \n\r\t\v\x00\\");
			$function_name = trim($function_name);
			if ($class_name === '' || $function_name === '') {
				throw new \InvalidArgumentException('uopz method target must be in the form ClassName::methodName');
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
	 * Normalize phase aliases.
	 */
	public static function normalize_phase(string $phase): string {
		$phase = strtolower(trim($phase));

		return match ($phase) {
			'before' => 'enter',
			'after' => 'exit',
			'enter', 'exit', 'around', 'replace' => $phase,
			default => throw new \InvalidArgumentException('Invalid uopz hook phase. Valid names: enter, exit, before, after, around, replace.'),
		};
	}
}
