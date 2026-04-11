<?php

namespace frytimo\fusor\resources\classes;

class fusor_event {
	public function __construct(
		public string $name,
		public readonly uuid $uuid = new uuid(),
		public array $data = [],
	) {
	}

	public function __get(string $name) {
		return $this->data[$name] ?? null;
	}
}
