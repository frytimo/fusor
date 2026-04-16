<?php

namespace frytimo\fusor\resources\attributes;

/**
 * Shared HTTP attribute base class.
 *
 * Provides normalized path and stage handling so concrete
 * HTTP method attributes can reuse the same logic.
 *
 * @package frytimo\fusor\resources\attributes
 */
abstract class http extends on {
	public readonly string $method;
	public readonly string $path;
	public readonly string $stage;

	/**
	 * Construct.
	 * @param mixed $method
	 * @param mixed $path
	 * @param mixed $stage
	 * @param mixed $priority
	 * @return mixed
	 */
	public function __construct(string $method, string $path = '*', string $stage = 'before', int $priority = 0) {
		$normalized_method = self::normalize_method($method);
		$normalized_path = self::normalize_path($path);
		$normalized_stage = self::normalize_stage($stage);

		parent::__construct(
			event_name: $normalized_stage . '_http_' . $normalized_method . ':' . $normalized_path,
			priority: $priority,
		);

		$this->method = $normalized_method;
		$this->path = $normalized_path;
		$this->stage = $normalized_stage;
	}

	/**
	 * Normalize method.
	 * @param mixed $method
	 * @return string
	 */
	protected static function normalize_method(string $method): string {
		$method = strtolower(trim($method));
		$method = preg_replace('/[^a-z0-9_]+/', '', $method) ?? '';

		if ($method === '') {
			throw new \InvalidArgumentException('HTTP method must not be empty');
		}

		return $method;
	}

	/**
	 * Normalize stage.
	 * @param mixed $stage
	 * @return string
	 */
	protected static function normalize_stage(string $stage): string {
		$stage = strtolower(trim($stage));
		return $stage === 'after' ? 'after' : 'before';
	}

	/**
	 * Normalize path.
	 * @param mixed $path
	 * @return string
	 */
	protected static function normalize_path(string $path): string {
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
