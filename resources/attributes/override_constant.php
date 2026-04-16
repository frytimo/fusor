<?php

namespace Frytimo\Fusor\resources\attributes;

use Attribute;

/**
 * Declaratively override a global or class constant when uopz is available.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class override_constant extends on {
	public readonly string $target;
	public readonly mixed $value;
	public readonly ?string $class_name;
	public readonly string $constant_name;
	public readonly bool $is_class_constant;

	/**
	 * Construct.
	 */
	public function __construct(string $target, mixed $value = null, int $priority = 0) {
		$target = trim($target, " \n\r\t\v\x00\\");
		if ($target === '') {
			throw new \InvalidArgumentException('constant target must not be empty');
		}

		parent::__construct(event_name: 'constant_override', priority: $priority);

		$this->target = $target;
		$this->value = $value;

		if (strpos($target, '::') !== false) {
			[$class_name, $constant_name] = explode('::', $target, 2);
			$class_name = trim($class_name, " \n\r\t\v\x00\\");
			$constant_name = trim($constant_name);
			if ($class_name === '' || $constant_name === '') {
				throw new \InvalidArgumentException('class constant target must be in the form ClassName::CONSTANT_NAME');
			}

			$this->is_class_constant = true;
			$this->class_name = $class_name;
			$this->constant_name = $constant_name;
		} else {
			$this->is_class_constant = false;
			$this->class_name = null;
			$this->constant_name = $target;
		}
	}
}
