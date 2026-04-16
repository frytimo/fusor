<?php

/**
 * Example 01: The #[on] Attribute — Named Event Listener
 *
 * The #[on] attribute is the foundation of Fusor's event system. It registers
 * a public static method as a listener for a named event. When that event is
 * dispatched, your method is called with a fusor_event object.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - Fusor installed at app/fusor/
 *   - No uopz extension needed
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * HOW IT WORKS:
 *   The auto_loader scans this file and finds the #[on] attribute on each
 *   public static method. The fusor_dispatcher registers these methods as
 *   listeners. When the event fires (e.g. a page renders), the method is
 *   called automatically.
 *
 * EVENT NAMING:
 *   Fusor fires these built-in events based on the page being rendered:
 *     - "before_render_{page}" — fired before the page output begins
 *     - "after_render_{page}"  — fired after the page output completes
 *   where {page} is the PHP filename without the .php extension.
 *
 *   Examples:
 *     bridges.php       → before_render_bridges / after_render_bridges
 *     extensions.php    → before_render_extensions / after_render_extensions
 *     bridge_edit.php   → before_render_bridge_edit / after_render_bridge_edit
 *     dialplans.php     → before_render_dialplans / after_render_dialplans
 *
 * PRIORITY:
 *   Lower numbers run first. Default is 0. Use negative numbers to run
 *   before other listeners, or high numbers to run last.
 *
 * @see app/fusor/resources/attributes/on.php
 * @see app/fusor/resources/classes/fusor_dispatcher.php
 * @see app/fusor/resources/bootstrap/120-dispatch.php
 */

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_01_on_event_listener {

	/**
	 * Run before the bridges list page renders.
	 *
	 * This fires immediately when /app/bridges/bridges.php is loaded,
	 * before any HTML output is sent to the browser.
	 *
	 * @param fusor_event $event The event object.
	 *        $event->name = "before_render_bridges"
	 *        $event->data = ['html' => &$html_output]
	 */
	#[on(event_name: 'before_render_bridges', priority: 0)]
	public static function before_bridges_page(fusor_event $event): void {
		// Log that someone is viewing the bridges page
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_01] before_render_bridges fired');
		closelog();
	}

	/**
	 * Run after the bridges list page has rendered.
	 *
	 * At this point, the full HTML output is captured in $event->data['html'].
	 * You can inspect or modify it before it is sent to the browser.
	 *
	 * @param fusor_event $event The event object.
	 *        $event->data['html'] contains the page HTML (by reference).
	 */
	#[on(event_name: 'after_render_bridges', priority: 0)]
	public static function after_bridges_page(fusor_event $event): void {
		// Append a comment to the page HTML
		if (isset($event->data['html']) && is_string($event->data['html'])) {
			$event->data['html'] .= "\n<!-- Rendered at " . date('c') . " by example_01 -->\n";
		}
	}

	/**
	 * Listen to bridge_edit page render events.
	 *
	 * The edit page for bridges uses the script name "bridge_edit.php",
	 * so the event name is "after_render_bridge_edit".
	 */
	#[on(event_name: 'after_render_bridge_edit', priority: 100)]
	public static function after_bridge_edit_page(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_01] after_render_bridge_edit fired');
		closelog();
	}

	/**
	 * Listen with high priority (runs before other listeners).
	 *
	 * Priority is sorted descending — higher numbers run first.
	 * Use this when you need to modify data before other hooks see it.
	 */
	#[on(event_name: 'before_render_extensions', priority: 100)]
	public static function early_extensions_hook(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_01] early extensions hook (priority 100)');
		closelog();
	}

	/**
	 * Listen with low priority (runs after other listeners).
	 */
	#[on(event_name: 'before_render_extensions', priority: -100)]
	public static function late_extensions_hook(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_01] late extensions hook (priority -100)');
		closelog();
	}
}
