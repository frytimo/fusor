<?php

/**
 * Example 20: Dashboard Widget Hooks
 *
 * The FusionPBX dashboard (core/dashboard/index.php) renders widgets using
 * procedural include files — there are no class methods to hook with uopz.
 *
 * However, you can use HTTP GET hooks to modify the dashboard output:
 *   - 'before' stage: inject data, set variables, or redirect
 *   - 'after' stage: modify the rendered HTML (add widgets, banners, scripts)
 *
 * You can also use page render events:
 *   - before_render_index  (dashboard uses index.php)
 *   - after_render_index
 *
 * DASHBOARD URL: /core/dashboard/index.php (also accessible as /)
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
 * @see app/fusor/resources/attributes/http_get.php
 * @see app/fusor/resources/attributes/on.php
 */

use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_20_dashboard_widget_hooks {

	/**
	 * Inject a custom widget into the dashboard via HTML modification.
	 *
	 * This 'after' hook captures the dashboard HTML and inserts a custom
	 * widget card. Production implementations would create proper dashboard
	 * widgets via the widget system, but this demonstrates the hook mechanism.
	 */
	#[http_get(path: '/core/dashboard/index.php', stage: 'after')]
	public static function inject_dashboard_widget(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		// Only inject for authenticated users
		if (empty($_SESSION['username'])) {
			return;
		}

		$widget_html = '<div class="card mt-2" style="border-left:4px solid #17a2b8;">'
			. '<div class="card-body">'
			. '<h5 class="card-title">Fusor Custom Widget</h5>'
			. '<p class="card-text">Dashboard modified by Fusor example at ' . date('H:i:s') . '</p>'
			. '</div></div>';

		// Insert before the closing main content area
		$event->data['html'] = str_replace(
			'</body>',
			$widget_html . "\n</body>",
			$event->data['html']
		);
	}

	/**
	 * Log dashboard access using the page render event.
	 *
	 * The dashboard uses "index.php" as its filename, so the event
	 * names are before_render_index and after_render_index.
	 *
	 * NOTE: "before_render_index" fires for ANY page named index.php,
	 * not just the dashboard. Check the script path if you need to
	 * distinguish between different index.php files.
	 */
	#[on(event_name: 'before_render_index', priority: 0)]
	public static function before_dashboard_render(fusor_event $event): void {
		$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

		// Only act on the actual dashboard, not other index.php files
		if (strpos($script, '/core/dashboard/') === false) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_20] Dashboard accessed by ' . ($_SESSION['username'] ?? 'anonymous'));
		closelog();
	}

	/**
	 * Add custom JavaScript to the dashboard.
	 *
	 * Use the after_render_index event to inject JavaScript that runs
	 * after the dashboard is fully loaded.
	 */
	#[on(event_name: 'after_render_index', priority: -10)]
	public static function inject_dashboard_script(fusor_event $event): void {
		$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
		if (strpos($script, '/core/dashboard/') === false) {
			return;
		}

		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		$js = '<script>document.addEventListener("DOMContentLoaded",function(){console.log("[example_20] Dashboard JS loaded at ' . date('c') . '");});</script>';

		$event->data['html'] = str_replace('</body>', $js . "\n</body>", $event->data['html']);
	}
}
