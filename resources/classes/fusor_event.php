<?php

namespace frytimo\fusor\resources\classes;

/**
 * Fusor event.
 */
class fusor_event {
	/**
	 * Construct.
	 * @param mixed $name
	 * @param mixed $uuid
	 * @return mixed
	 */
	public function __construct(
		public string $name,
		public readonly uuid $uuid = new uuid(),
		public array $data = [],
	) {
	}

	/**
	 * Get.
	 * @param mixed $name
	 * @return mixed
	 */
	public function __get(string $name) {
		return $this->data[$name] ?? null;
	}

	/**
	 * Determine whether the event contains query parameters.
	 * @return bool
	 */
	public function has_query_params(): bool {
		return $this->get_query_string() !== '';
	}

	/**
	 * Build the event query string.
	 * @return string
	 */
	public function get_query_string(): string {
		$query = $this->data['query'] ?? null;

		if (is_string($query)) {
			return ltrim($query, '?');
		}

		if (!is_array($query) || empty($query)) {
			return '';
		}

		$query_string = http_build_query($query);
		return is_string($query_string) ? $query_string : '';
	}
}

