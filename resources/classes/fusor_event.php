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
}

