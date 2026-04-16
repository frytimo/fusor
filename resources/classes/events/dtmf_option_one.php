<?php

declare(strict_types=1);

namespace Frytimo\Fusor\resources\classes\events;

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;
use Frytimo\Fusor\resources\classes\fusor_service;

/**
 * Dtmf option one.
 */
class dtmf_option_one {

	#[on(event_name: 'switch.dtmf', priority: 0)]
	/**
	 * Handle.
	 * @param mixed $fusor_event
	 * @return void
	 */
	public static function handle(fusor_event $fusor_event): void {
		$event = is_array($fusor_event->event) ? $fusor_event->event : [];
		$digit = (string) ($event['DTMF-Digit'] ?? '');
		if ($digit !== '1') {
			return;
		}

		$channel_uuid = (string) ($event['Unique-ID'] ?? '');
		$client = $fusor_event->client;
		$service = $fusor_event->service;

		if ($channel_uuid === '' || !is_resource($client) || !$service instanceof fusor_service) {
			return;
		}

		$service->execute_app($client, $channel_uuid, 'playback', 'ivr/ivr-thank_you.wav');
	}
}

