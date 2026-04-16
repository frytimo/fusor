<?php

/**
 * Example 14: Page Render Hooks — Inject HTML Before/After Pages
 *
 * The Fusor bootstrap (120-dispatch.php) fires "before_render_{page}" and
 * "after_render_{page}" events for every page load. The {page} name is
 * derived from the script filename without the .php extension.
 *
 * These hooks use the #[on] attribute and do NOT require the uopz extension.
 *
 * PAGE NAME MAPPING:
 *   Script File                          Event Names
 *   ───────────────────────────────────  ──────────────────────────────────
 *   /app/bridges/bridges.php           → before_render_bridges / after_render_bridges
 *   /app/bridges/bridge_edit.php       → before_render_bridge_edit / after_render_bridge_edit
 *   /app/extensions/extensions.php     → before_render_extensions / after_render_extensions
 *   /app/extensions/extension_edit.php → before_render_extension_edit / after_render_extension_edit
 *   /app/dialplans/dialplans.php       → before_render_dialplans / after_render_dialplans
 *   /core/dashboard/index.php          → before_render_index / after_render_index
 *   /app/ring_groups/ring_groups.php   → before_render_ring_groups / after_render_ring_groups
 *   /app/gateways/gateways.php         → before_render_gateways / after_render_gateways
 *   /app/ivr_menus/ivr_menus.php       → before_render_ivr_menus / after_render_ivr_menus
 *   /app/call_centers/call_centers.php → before_render_call_centers / after_render_call_centers
 *
 * BEFORE vs AFTER:
 *   "before_render_*" fires before the page outputs anything.
 *   $event->data['html'] is empty — use it to set up state or inject early HTML.
 *
 *   "after_render_*" fires after all output is captured.
 *   $event->data['html'] contains the full page HTML (by reference).
 *   Modify it to inject scripts, change content, add tracking.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - No uopz needed
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * @see app/fusor/resources/attributes/on.php
 * @see app/fusor/resources/bootstrap/120-dispatch.php
 */

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_14_page_render_hooks {

	/**
	 * Inject a custom CSS banner on the bridges list page.
	 *
	 * Modifies the HTML after the page has rendered. The HTML is in
	 * $event->data['html'] and is passed by reference — changes stick.
	 */
	#[on(event_name: 'after_render_bridges', priority: 0)]
	public static function add_banner_to_bridges(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		$banner = '<div style="background:#17a2b8;color:#fff;padding:8px 16px;text-align:center;font-size:13px;">'
			. 'Custom banner injected by Fusor example_14'
			. '</div>';

		// Insert the banner right after the opening <body> tag
		$event->data['html'] = preg_replace(
			'/(<body[^>]*>)/i',
			'$1' . $banner,
			$event->data['html'],
			1
		) ?? $event->data['html'];
	}

	/**
	 * Inject a JavaScript snippet before </body> on the extensions page.
	 *
	 * This pattern is useful for adding custom behavior to any page
	 * without modifying the original PHP files.
	 */
	#[on(event_name: 'after_render_extensions', priority: 0)]
	public static function inject_extensions_script(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		$script = '<script>console.log("[example_14] Extensions page loaded at ' . date('c') . '");</script>';

		$event->data['html'] = str_replace(
			'</body>',
			$script . "\n</body>",
			$event->data['html']
		);
	}

	/**
	 * Log before a page starts rendering.
	 *
	 * "before_render_*" events fire before the page outputs anything.
	 * This is useful for setting up state, checking permissions, or
	 * starting timers.
	 */
	#[on(event_name: 'before_render_dialplans', priority: 0)]
	public static function before_dialplans_render(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_14] Dialplans page is about to render');
		closelog();
	}

	/**
	 * Modify the bridge edit page HTML.
	 *
	 * This is a real-world pattern used in the FusionPBX bridges app.
	 * @see app/bridges/resources/classes/bridge_inject.php
	 */
	#[on(event_name: 'after_render_bridge_edit', priority: 50)]
	public static function modify_bridge_edit(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		// Add a timestamp comment to track when the page was assembled
		$event->data['html'] .= "\n<!-- Bridge edit page modified by example_14 at " . date('c') . " -->\n";
	}

	/**
	 * Add custom meta tags to any page.
	 *
	 * Inject meta tags into the <head> section for SEO, security headers,
	 * or custom metadata.
	 */
	#[on(event_name: 'after_render_gateways', priority: 0)]
	public static function add_meta_to_gateways(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		$meta = '<meta name="fusor-version" content="1.0" />';
		$event->data['html'] = str_replace(
			'</head>',
			$meta . "\n</head>",
			$event->data['html']
		);
	}
}
