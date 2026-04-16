<?php

/**
 * Example 13: Logout Hooks — Intercept and Redirect
 *
 * FusionPBX logout is procedural (logout.php), not class-based. There is
 * no method to hook with on_method_*. Instead, use HTTP lifecycle hooks
 * to intercept the logout request.
 *
 * THE LOGOUT FLOW:
 *   1. User clicks logout → GET /logout.php
 *   2. logout.php destroys the session
 *   3. logout.php redirects to the login page
 *
 * AVAILABLE HOOKS:
 *   - #[http_get('/logout.php', stage: 'before')]  — runs BEFORE session destruction
 *   - #[http_get('/logout.php', stage: 'after')]   — runs AFTER page output (redirect issued)
 *   - #[http_post('/logout.php', stage: 'before')] — if logout is ever POST-based
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
 * @see app/fusor/resources/attributes/http_get.php
 */

use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_13_logout_hooks {

	/**
	 * Log the user's last session data before logout destroys it.
	 *
	 * The 'before' stage fires before logout.php runs, so the session
	 * is still intact. This is the only time you can access session
	 * variables ($_SESSION) during logout.
	 *
	 * Use this for:
	 *   - Audit logging
	 *   - Cleanup tasks
	 *   - Revoking external tokens
	 *   - Recording the last active time
	 */
	#[http_get(path: '/logout.php', stage: 'before')]
	public static function before_logout(fusor_event $event): void {
		$user_uuid = $_SESSION['user_uuid'] ?? 'unknown';
		$domain = $_SESSION['domain_name'] ?? 'unknown';
		$username = $_SESSION['username'] ?? 'unknown';
		$remote_ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

		openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
		syslog(LOG_INFO, '[example_13] Logout — user: ' . $username . ' domain: ' . $domain . ' uuid: ' . $user_uuid . ' ip: ' . $remote_ip);
		closelog();
	}

	/**
	 * Redirect to a custom URL after logout.
	 *
	 * The 'after' stage fires after logout.php has processed. Use high
	 * priority to ensure your redirect runs before the default one.
	 *
	 * Common use cases:
	 *   - Redirect to an SSO logout endpoint
	 *   - Redirect to a corporate portal
	 *   - Show a custom "logged out" page
	 */
	#[http_get(path: '/logout.php', stage: 'after', priority: 1000)]
	public static function redirect_after_logout(fusor_event $event): void {
		// Uncomment to redirect to a custom URL after logout:
		// header('Location: https://your-sso-provider.com/logout', true, 302);
		// exit();

		openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
		syslog(LOG_INFO, '[example_13] Could redirect after logout');
		closelog();
	}

	/**
	 * Clean up external resources before logout.
	 *
	 * If your deployment integrates with external systems (APIs, tokens,
	 * webhooks), clean them up before the session is destroyed.
	 */
	#[http_get(path: '/logout.php', stage: 'before', priority: 100)]
	public static function cleanup_before_logout(fusor_event $event): void {
		// Example: revoke an API token stored in the session
		$api_token = $_SESSION['external_api_token'] ?? '';
		if ($api_token !== '') {
			openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
			syslog(LOG_INFO, '[example_13] Revoking external API token before logout');
			closelog();

			// Example: call an external API to revoke the token
			// $ch = curl_init('https://api.example.com/revoke');
			// curl_setopt($ch, CURLOPT_POST, true);
			// curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_token]);
			// curl_exec($ch);
			// curl_close($ch);
		}
	}
}
