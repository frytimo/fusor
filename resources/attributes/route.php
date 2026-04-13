<?php

namespace frytimo\fusor\resources\attributes;

use Attribute;

/**
 * Description of Route
 *
 * @author Tim Fry <tim.fry@hotmail.com>
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
/**
 * Route.
 */
class route {
	public string $path;
	public string $method;

	/**
	 * Construct.
	 * @param mixed $path
	 * @return mixed
	 */
	public function __construct(string $path) {
		$this->path = $path;
		$this->method = strtolower(basename(strtr(static::class, '\\', DIRECTORY_SEPARATOR)));
	}
}

