<?php

namespace frytimo\fusor\resources\attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class http_get extends on {
	public readonly string $path;
	public readonly string $stage;

	/**
	 * Construct.
	 * @param mixed $path
	 * @param mixed $stage
	 * @param mixed $priority
	 * @return mixed
	 */
	public function __construct(string $path = '*', string $stage = 'before', int $priority = 0) {
		$normalized_path = self::normalize_path($path);
		$normalized_stage = self::normalize_stage($stage);

		parent::__construct(
			event_name: $normalized_stage . '_http_get:' . $normalized_path,
			priority: $priority,
		);

		$this->path = $normalized_path;
		$this->stage = $normalized_stage;
	}

	/**
	 * Normalize stage.
	 * @param mixed $stage
	 * @return string
	 */
	private static function normalize_stage(string $stage): string {
		$stage = strtolower(trim($stage));
		return $stage === 'after' ? 'after' : 'before';
	}

	/**
	 * Normalize path.
	 * @param mixed $path
	 * @return string
	 */
	private static function normalize_path(string $path): string {
		$path = trim($path);
		if ($path === '' || $path === '*') {
			return '*';
		}

		$path = '/' . ltrim($path, '/');
		$path = preg_replace('#/+#', '/', $path);

		if ($path !== '/' && str_ends_with($path, '/')) {
			$path = rtrim($path, '/');
		}

		return $path;
	}
}