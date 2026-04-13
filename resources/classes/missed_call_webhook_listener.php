<?php

namespace frytimo\fusor\resources\classes;

use frytimo\fusor\resources\attributes\on;

/**
 * Missed call webhook listener.
 */
final class missed_call_webhook_listener {
	private static int $handled_count = 0;

	#[on('call.missed', 100)]
	/**
	 * Handle.
	 * @param mixed $fusor_event
	 * @return void
	 */
	public static function handle(fusor_event $fusor_event): void {
		++self::$handled_count;

		// Handle the missed call event here.
		// For example, you could log the event or send a notification.
	}

	/**
	 * Reset handled count.
	 * @return void
	 */
	public static function reset_handled_count(): void {
		self::$handled_count = 0;
	}

	/**
	 * Get handled count.
	 * @return int
	 */
	public static function get_handled_count(): int {
		return self::$handled_count;
	}

}
