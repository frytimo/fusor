<?php

/**
 * Example 02: The #[http_get] Attribute — GET Request Hook
 *
 * The #[http_get] attribute hooks into GET requests for specific URL paths.
 * You can intercept requests before the page runs (stage: 'before') or
 * after the page output is captured (stage: 'after').
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
 * PATH MATCHING:
 *   - Exact paths:  '/app/bridges/bridges.php'
 *   - Wildcard all:  '*' (matches every GET request)
 *   - Paths are normalized: leading slash is added, trailing slash removed,
 *     double slashes collapsed.
 *
 * STAGES:
 *   - 'before' — fires before the page script executes.
 *     Use for: redirects, access control, injecting data into globals.
 *   - 'after' — fires after page output is captured.
 *     Use for: HTML modification, adding headers, logging page views.
 *
 * @see app/fusor/resources/attributes/http_get.php
 * @see app/fusor/resources/attributes/http.php (base class)
 * @see app/fusor/resources/classes/http_route_hook_dispatcher.php
 * @see app/fusor/resources/bootstrap/140-http-route-hooks.php
 */

use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_02_http_get_hook {

	/**
	 * Log every GET request to the bridges list page.
	 *
	 * This fires BEFORE bridges.php executes, so the page has not
	 * produced any output yet.
	 *
	 * @param fusor_event $event
	 *        $event->method = "GET"
	 *        $event->path   = "/app/bridges/bridges.php"
	 *        $event->query  = $_GET array
	 */
	#[http_get(path: '/app/bridges/bridges.php', stage: 'before')]
	public static function before_bridges_get(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_02] GET /app/bridges/bridges.php — query: ' . json_encode($event->query ?? [], JSON_UNESCAPED_SLASHES));
		closelog();
	}

	/**
	 * Modify the bridges page HTML after it renders.
	 *
	 * The 'after' stage captures all output in $event->data['html'].
	 * You can modify this string — it's passed by reference.
	 */
	#[http_get(path: '/app/bridges/bridges.php', stage: 'after')]
	public static function after_bridges_get(fusor_event $event): void {
		if (isset($event->data['html']) && is_string($event->data['html'])) {
			// Inject a tracking comment into the page footer
			$event->data['html'] = str_replace(
				'</body>',
				'<!-- GET hook by example_02 at ' . date('c') . ' --></body>',
				$event->data['html']
			);
		}
	}

	/**
	 * Redirect from one page to another.
	 *
	 * Intercept GET requests to a page and redirect the user elsewhere.
	 * This is useful for replacing built-in pages with custom versions.
	 *
	 * IMPORTANT: Always call exit() after header('Location: ...') to
	 * prevent the original page from executing.
	 */
	#[http_get(path: '/app/dialplans/dialplans.php', stage: 'before', priority: 1000)]
	public static function redirect_dialplans(fusor_event $event): void {
		// Uncomment the lines below to activate the redirect:
		// header('Location: /app/enhanced_dialplans/dialplans.php', true, 302);
		// exit();

		// For this example, we just log that we could have redirected
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_02] Could redirect /app/dialplans/dialplans.php');
		closelog();
	}

	/**
	 * Global GET hook — fires on every GET request.
	 *
	 * Use path: '*' to match all GET requests. Be careful with performance
	 * since this runs on every single page load.
	 */
	#[http_get(path: '*', stage: 'before', priority: -1000)]
	public static function global_get_logger(fusor_event $event): void {
		// Only log when debug mode is active to avoid performance impact
		if (empty($_REQUEST['debug'])) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_02] Global GET: ' . ($event->path ?? 'unknown'));
		closelog();
	}

	/**
	 * Access control example — restrict a page by IP.
	 *
	 * This shows how to use a 'before' hook for access control.
	 * The request is blocked before the page has a chance to run.
	 */
	#[http_get(path: '/app/event_guard/event_guard.php', stage: 'before', priority: 999)]
	public static function restrict_event_guard(fusor_event $event): void {
		$remote_ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
		$allowed_ips = ['127.0.0.1', '::1'];

		if (!in_array($remote_ip, $allowed_ips, true)) {
			// Uncomment to enforce:
			// http_response_code(403);
			// echo 'Access denied.';
			// exit();

			openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
			syslog(LOG_WARNING, '[example_02] Blocked access to event_guard from ' . $remote_ip);
			closelog();
		}
	}
}
