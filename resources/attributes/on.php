<?php

namespace frytimo\fusor\resources\attributes;

use \Attribute;

/**
 * On.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
/**
 * On.
 */
final readonly class on {
	/**
	 * Construct.
	 * @param mixed $event_name
	 * @param mixed $priority
	 * @return mixed
	 */
	public function __construct(
		public string $event_name,
		public int $priority = 0,
	) {
	}
}

