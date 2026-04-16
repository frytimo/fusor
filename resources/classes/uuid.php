<?php

namespace Frytimo\Fusor\resources\classes;

/**
 * Uuid.
 */
class uuid implements \Stringable {
	private string $uuid;

	/**
	 * Construct.
	 * @param mixed $uuid
	 * @return mixed
	 */
	public function __construct(string $uuid = '') {
		if (static::is_uuid($uuid))
			$this->uuid = $uuid;
		else {
			$os = strtolower(substr(PHP_OS, 0, 3));
			switch ($os) {
				case 'fre':
				case 'lin':
				case 'win':
					$this->uuid = static::$os();
					break;
			}
		}
	}

	/**
	 * To String.
	 * @return mixed
	 */
	public function __toString() {
		return $this->uuid;
	}

	/**
	 * Is uuid.
	 * @param mixed $uuid
	 * @return bool
	 */
	public static function is_uuid(string|self $uuid): bool {
		$is_uuid = false;
		if (gettype($uuid) == 'string' || $uuid instanceof self) {
			if (substr_count($uuid, '-') != 0 && strlen($uuid) == 36) {
				$regex   = '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i';
				$is_uuid = preg_match($regex, $uuid);
			} else if (strlen(preg_replace("#[^a-fA-F0-9]#", '', $uuid)) == 32) {
				$regex   = '/^[0-9A-F]{32}$/i';
				$is_uuid = preg_match($regex, $uuid);
			}
		}

		return $is_uuid;
	}

	/**
	 * Fre.
	 * @return mixed
	 */
	private static function fre() {
		$uuid = trim(shell_exec("uuid -v 4"));
		if (static::is_uuid($uuid)) {
			return $uuid;
		} else {
			echo "Please install the following package.\n";
			echo "pkg install ossp-uuid\n";
			exit;
		}
	}

	/**
	 * Lin.
	 * @return string
	 */
	private static function lin(): string {
		return trim(file_get_contents('/proc/sys/kernel/random/uuid'));
	}

	/**
	 * Win.
	 * @return string
	 */
	private static function win(): string {
		$uuid = trim(com_create_guid(), '{}');
		if (static::is_uuid($uuid))
			return $uuid;
		throw new \Exception("The com_create_guid() function failed to create a uuid.");
	}
}

