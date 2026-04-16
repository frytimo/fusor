<?php

/**
 * Example 03: The #[http_post] Attribute — POST Request Hook
 *
 * The #[http_post] attribute hooks into POST requests for specific URL paths.
 * Use it to intercept form submissions, API calls, and data mutations.
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
 * POST EVENT DATA:
 *   $event->method     — "POST"
 *   $event->path       — Request path
 *   $event->body       — $_POST array (raw)
 *   $event->body_safe  — Filtered POST parameters
 *   $event->query      — $_GET array (raw)
 *
 * @see app/fusor/resources/attributes/http_post.php
 * @see app/fusor/resources/attributes/http.php (base class)
 * @see app/fusor/resources/classes/http_route_hook_dispatcher.php
 */

use Frytimo\Fusor\resources\attributes\http_post;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_03_http_post_hook {

	/**
	 * Log all POST submissions to the extensions edit page.
	 *
	 * This fires BEFORE the page script processes the POST data, so you
	 * can validate, modify $_POST, or block the submission entirely.
	 *
	 * @param fusor_event $event Contains POST body, query params, path
	 */
	#[http_post(path: '/app/extensions/extension_edit.php', stage: 'before')]
	public static function before_extension_save(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);

		// Log the extension being saved
		$extension = '';
		if (isset($event->body['extension']) && is_string($event->body['extension'])) {
			$extension = $event->body['extension'];
		}

		syslog(LOG_INFO, '[example_03] POST to extension_edit — extension: ' . $extension);
		closelog();
	}

	/**
	 * After a bridge is saved, log the outcome.
	 *
	 * The 'after' stage fires once the page has completed processing.
	 * The response HTML is available in $event->data['html'].
	 */
	#[http_post(path: '/app/bridges/bridge_edit.php', stage: 'after')]
	public static function after_bridge_save(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_03] POST to bridge_edit completed');
		closelog();
	}

	/**
	 * Validate POST data before processing.
	 *
	 * This example shows how to intercept and validate a form submission.
	 * If validation fails, you can show an error and stop processing.
	 */
	#[http_post(path: '/app/gateways/gateway_edit.php', stage: 'before', priority: 100)]
	public static function validate_gateway_post(fusor_event $event): void {
		// Check if a required field is missing from the POST
		$gateway_name = '';
		if (isset($event->body['gateway_name']) && is_string($event->body['gateway_name'])) {
			$gateway_name = trim($event->body['gateway_name']);
		}

		if ($gateway_name === '') {
			// Log the validation failure
			openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
			syslog(LOG_WARNING, '[example_03] Gateway POST missing gateway_name');
			closelog();

			// Uncomment to block the save:
			// $_SESSION['message'] = 'Gateway name is required.';
			// header('Location: /app/gateways/gateway_edit.php');
			// exit();
		}
	}

	/**
	 * Intercept login POST submissions.
	 *
	 * This demonstrates hooking the login form POST to add custom
	 * logging before authentication is processed.
	 */
	#[http_post(path: '/resources/login.php', stage: 'before')]
	public static function before_login_post(fusor_event $event): void {
		$username = '';
		if (isset($event->body['username']) && is_string($event->body['username'])) {
			$username = trim($event->body['username']);
		}

		if ($username !== '') {
			$remote_ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
			openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
			syslog(LOG_INFO, '[example_03] Login attempt from ' . $remote_ip . ' user: ' . $username);
			closelog();
		}
	}

	/**
	 * After login POST — check the authentication result.
	 *
	 * By using stage 'after', the authentication system has already
	 * processed the login. You can check $_SESSION for the result.
	 */
	#[http_post(path: '/resources/login.php', stage: 'after')]
	public static function after_login_post(fusor_event $event): void {
		$authorized = !empty($_SESSION['authorized']);
		$username = '';
		if (isset($event->body['username']) && is_string($event->body['username'])) {
			$username = trim($event->body['username']);
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(
			$authorized ? LOG_INFO : LOG_WARNING,
			'[example_03] Login ' . ($authorized ? 'SUCCESS' : 'FAILURE') . ' user: ' . $username
		);
		closelog();
	}

	/**
	 * Global POST logger for debugging.
	 *
	 * Matches all POST requests with wildcard '*'. Only active when
	 * debug mode is requested.
	 */
	#[http_post(path: '*', stage: 'before', priority: -1000)]
	public static function debug_post_logger(fusor_event $event): void {
		if (empty($_REQUEST['debug'])) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_03] Global POST: ' . ($event->path ?? 'unknown') . ' keys: ' . implode(',', array_keys($event->body ?? [])));
		closelog();
	}
}
