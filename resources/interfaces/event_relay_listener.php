<?php

declare(strict_types=1);

namespace frytimo\fusor\resources\interfaces;

/**
 * Event relay listener.
 */
interface event_relay_listener {

	/**
	 * Return the FreeSWITCH event this listener handles.
	 */
	public static function register_event_name(): string;

	/**
	 * Execute listener logic for the event payload.
	 */
	public function event_triggered();
}

