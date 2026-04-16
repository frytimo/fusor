<?php

namespace Frytimo\Fusor\resources\classes;

/**
 * Fusor event.
 */
class fusor_event {
	public readonly ?string $target;

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
		$this->target = isset($this->data['target']) ? (string) $this->data['target'] : null;
	}

	/**
	 * Get target.
	 * @return string|null
	 */
	public function target(): ?string {
		return $this->target;
	}

	/**
	 * Get.
	 * @param mixed $name
	 * @return mixed
	 */
	public function __get(string $name) {
		if ($name === 'target') {
			return $this->target;
		}

		return $this->data[$name] ?? null;
	}

}
