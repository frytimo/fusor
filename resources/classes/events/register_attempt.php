<?php

declare(strict_types=1);

namespace Frytimo\Fusor\resources\classes\events;

use Frytimo\Fusor\resources\interfaces\event_relay_listener;

/**
 * Register attempt.
 */
class register_attempt implements event_relay_listener {

	protected array $event_arr;
	protected string $uuid;
	protected array $context;

	/**
	 * Construct.
	 * @param mixed $uuid
	 * @param mixed $event_arr
	 * @param mixed $context
	 * @return mixed
	 */
	public function __construct(string $uuid, array $event_arr, array $context = []) {
		$this->event_arr = $event_arr;
		$this->uuid = $uuid;
		$this->context = $context;
	}

	/**
	 * Parse user agent mac.
	 * @return string
	 */
	private function parse_user_agent_mac(): string {
		$user_agent = (string) ($this->event_arr['user-agent'] ?? $this->event_arr['User-Agent'] ?? '');
		$pattern = '/<(.*)>/';
		preg_match($pattern, $user_agent, $matches);
		if (count($matches) > 1) {
			return (string) $matches[1];
		}
		return '';
	}

	/**
	 * Event triggered.
	 * @return mixed
	 */
	public function event_triggered() {
		$remote_ip = (string) ($this->event_arr['network-ip'] ?? $this->event_arr['Network-IP'] ?? 'unknown');
		$device_uuid = $this->parse_user_agent_mac();

		error_log("[fusor register_attempt] register attempt from: {$remote_ip}");
		if ($device_uuid !== '') {
			error_log("[fusor register_attempt] device_uuid: {$device_uuid}");
		}

		$counter_callback = $this->context['args']['counter_callback'] ?? null;
		if (is_callable($counter_callback)) {
			$counter_callback($remote_ip, $device_uuid, $this->event_arr);
		}
	}

	/**
	 * Register event name.
	 * @return string
	 */
	public static function register_event_name(): string {
		return 'SOFIA::REGISTER_ATTEMPT';
	}
}


