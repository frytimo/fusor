<?php

/**
 * Example 19: Wildcard and Glob Event Patterns
 *
 * The Fusor event dispatcher supports fnmatch()-style wildcard patterns
 * when registering event listeners. This lets a single listener handle
 * families of related events.
 *
 * PATTERN SYNTAX (fnmatch):
 *   '*'    — matches any sequence of characters
 *   '?'    — matches exactly one character
 *   '[..]' — matches one character from the set
 *
 * EXAMPLES:
 *   'before_render_*'           — matches all before_render events
 *   'after_render_bridge*'      — matches after_render_bridge_edit, after_render_bridges
 *   'sofia::*'                  — matches all FreeSWITCH sofia events
 *   'before_http_get:*'         — matches all GET hooks  (internal event name)
 *   '/*'                        — matches one level of path events
 *   '/**\/*'                    — matches any depth (double-star support)
 *
 * DIRECTORY WILDCARD BEHAVIOR:
 *   When the .env setting match_directory_on_wildcard=true,
 *   a pattern like '/core/dashboard/*' also matches '/core/dashboard' itself
 *   (without a trailing segment).
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - No uopz extension needed
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * @see app/fusor/resources/classes/fusor_dispatcher.php (event_matches method)
 */

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_19_wildcard_event_listener {

	/**
	 * Match all "before_render_*" events — fires for every page.
	 *
	 * This single listener handles the before_render event for ALL pages:
	 *   before_render_bridges, before_render_extensions, before_render_dialplans, etc.
	 *
	 * Use $event->name to determine WHICH page triggered the event.
	 */
	#[on(event_name: 'before_render_*', priority: -1000)]
	public static function before_any_render(fusor_event $event): void {
		// Only active when debug mode is on
		if (empty($_REQUEST['debug'])) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_19] Wildcard match: ' . ($event->name ?? 'unknown'));
		closelog();
	}

	/**
	 * Match all "after_render_*" events.
	 *
	 * Combined with the before_render wildcard above, you can track
	 * every page load's lifecycle.
	 */
	#[on(event_name: 'after_render_*', priority: -1000)]
	public static function after_any_render(fusor_event $event): void {
		if (empty($_REQUEST['debug'])) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_19] After render wildcard: ' . ($event->name ?? 'unknown'));
		closelog();
	}

	/**
	 * Match all sofia (FreeSWITCH SIP) events.
	 *
	 * Catches: sofia::register_attempt, sofia::register_failure,
	 * sofia::unregister, sofia::pre_register, etc.
	 */
	#[on(event_name: 'sofia::*', priority: -10)]
	public static function all_sofia_events(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_19] Sofia event: ' . ($event->name ?? 'unknown'));
		closelog();
	}

	/**
	 * Match all bridge-related render events.
	 *
	 * The pattern 'after_render_bridge*' matches:
	 *   - after_render_bridges (list page)
	 *   - after_render_bridge_edit (edit page)
	 */
	#[on(event_name: 'after_render_bridge*', priority: 0)]
	public static function all_bridge_renders(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		$event->data['html'] .= "\n<!-- Wildcard bridge hook fired for: " . ($event->name ?? '') . " -->\n";
	}

	/**
	 * Match events with character sets.
	 *
	 * Using [bd] to match both before_render_bridges and before_render_destinations.
	 * (This is a contrived example to demonstrate the fnmatch syntax.)
	 */
	#[on(event_name: 'before_render_[bd]*', priority: -500)]
	public static function bridges_or_destinations_render(fusor_event $event): void {
		if (empty($_REQUEST['debug'])) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_19] [bd]* wildcard: ' . ($event->name ?? 'unknown'));
		closelog();
	}
}
