<?php

namespace Frytimo\Fusor\resources\attributes;

use Attribute;

/**
 * Convenience attribute for an enter-phase on_method hook.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class on_method_enter extends on_method {
	/**
	 * Construct.
	 */
	public function __construct(string $target, int $priority = 0) {
		parent::__construct(target: $target, event_name: 'enter', priority: $priority);
	}
}
