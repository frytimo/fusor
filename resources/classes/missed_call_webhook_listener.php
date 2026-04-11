<?php

namespace frytimo\fusor\resources\classes;

use frytimo\fusor\resources\attributes\on;

final class missed_call_webhook_listener {
	private static int $handled_count = 0;

	#[on('call.missed', 100)]
	public static function handle(fusor_event $fusor_event): void {
		++self::$handled_count;

		// Handle the missed call event here.
		// For example, you could log the event or send a notification.
	}

	public static function reset_handled_count(): void {
		self::$handled_count = 0;
	}

	public static function get_handled_count(): int {
		return self::$handled_count;
	}

}