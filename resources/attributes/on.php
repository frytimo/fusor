<?php

namespace frytimo\fusor\resources\attributes;

use \Attribute;

/**
 * The on attribute allows fusor to act upon an attribute that is tagged on a method
 * @package frytimo\fusor\resources\attributes
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class on {
	/**
	 * Construct.
	 * @param mixed $event_name
	 * @param mixed $priority
	 * @return mixed
	 */
	public function __construct(
		public readonly string $event_name,
		public readonly int $priority = 0,
	) {
	}
}
