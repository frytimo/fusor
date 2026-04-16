<?php

namespace frytimo\fusor\resources\attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class http_get extends http {
	/**
	 * Construct.
	 * @param mixed $path
	 * @param mixed $stage
	 * @param mixed $priority
	 * @return mixed
	 */
	public function __construct(string $path = '*', string $stage = 'before', int $priority = 0) {
		parent::__construct(
			method: 'get',
			path: $path,
			stage: $stage,
			priority: $priority,
		);
	}
}