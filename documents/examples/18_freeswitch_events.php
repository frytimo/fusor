<?php

/**
 * Example 18: FreeSWITCH Event Listeners
 *
 * When Fusor's event service is running, it relays FreeSWITCH Event Socket
 * events as named Fusor events. Your class can listen for these using the
 * #[on] attribute with the sofia:: event namespace.
 *
 * FREESWITCH EVENT NAMING:
 *   Events are dispatched with "sofia::" prefix plus the event subclass:
 *     'sofia::register_attempt'  — SIP registration attempt
 *     'sofia::register_failure'  — SIP registration failure
 *     'sofia::pre_register'      — SIP pre-registration
 *     'sofia::unregister'        — SIP unregistration
 *
 * CUSTOM EVENT NAMES:
 *   Fusor also supports custom event names that you define:
 *     'call.missed'              — custom event dispatched by application code
 *     'heartbeat'                — FreeSWITCH heartbeat event
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - Fusor event service running (app/fusor/resources/service/fusor.php)
 *   - No uopz needed
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *   4. Restart the Fusor event service to pick up new listeners
 *
 * @see app/fusor/resources/attributes/on.php
 * @see app/fusor/resources/classes/events/register_attempt.php
 * @see app/fusor/resources/classes/events/heartbeat.php
 * @see app/fusor/resources/classes/events/dtmf_option_one.php
 */

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_18_freeswitch_events {

	/**
	 * Handle SIP registration attempts.
	 *
	 * Fires when a SIP device attempts to register. The event data
	 * contains FreeSWITCH event headers like username, network IP, etc.
	 */
	#[on(event_name: 'sofia::register_attempt', priority: 0)]
	public static function handle_register_attempt(fusor_event $event): void {
		$username = (string) ($event->data['username'] ?? 'unknown');
		$network_ip = (string) ($event->data['network-ip'] ?? 'unknown');

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_18] SIP register attempt — user: ' . $username . ' ip: ' . $network_ip);
		closelog();
	}

	/**
	 * Handle registration failures — potential security events.
	 *
	 * Failed registrations may indicate brute-force attacks or
	 * misconfigured devices. Log them prominently.
	 */
	#[on(event_name: 'sofia::register_failure', priority: 10)]
	public static function handle_register_failure(fusor_event $event): void {
		$username = (string) ($event->data['username'] ?? 'unknown');
		$network_ip = (string) ($event->data['network-ip'] ?? 'unknown');

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_WARNING, '[example_18] SIP register FAILURE — user: ' . $username . ' ip: ' . $network_ip);
		closelog();
	}

	/**
	 * Handle device unregistration events.
	 */
	#[on(event_name: 'sofia::unregister', priority: 0)]
	public static function handle_unregister(fusor_event $event): void {
		$username = (string) ($event->data['username'] ?? 'unknown');

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_18] SIP unregister — user: ' . $username);
		closelog();
	}

	/**
	 * Handle custom application events.
	 *
	 * You can dispatch custom events from anywhere in your code and
	 * listen for them here. This decouples event producers from consumers.
	 *
	 * To dispatch this event from your code:
	 *   use Frytimo\Fusor\resources\classes\fusor_dispatcher;
	 *   use Frytimo\Fusor\resources\classes\fusor_event;
	 *
	 *   $event = new fusor_event('call.missed', data: ['caller' => '5551234567', 'callee' => '5559876543']);
	 *   fusor_dispatcher::dispatch($event);
	 */
	#[on(event_name: 'call.missed', priority: 0)]
	public static function handle_missed_call(fusor_event $event): void {
		$caller = (string) ($event->data['caller'] ?? 'unknown');
		$callee = (string) ($event->data['callee'] ?? 'unknown');

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_18] Missed call — from: ' . $caller . ' to: ' . $callee);
		closelog();
	}

	/**
	 * Handle FreeSWITCH heartbeat events.
	 *
	 * The heartbeat event fires periodically from FreeSWITCH and can
	 * be used for health monitoring.
	 */
	#[on(event_name: 'heartbeat', priority: 0)]
	public static function handle_heartbeat(fusor_event $event): void {
		// Heartbeat events are frequent — don't log every one
		// This is here to demonstrate the event name pattern
	}
}
