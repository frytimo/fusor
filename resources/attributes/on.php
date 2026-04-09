<?php

namespace fusor\resources\attributes;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class on {
	public function __construct(
		public string $event_name,
		public int $priority = 0,
	) {
	}
}
