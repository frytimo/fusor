<?php

/**
 * Example 12: Complete Login Page Customization
 *
 * This comprehensive example shows how to hook multiple aspects of the
 * FusionPBX login flow using a combination of Fusor attributes:
 *
 *   - #[http_get]        — Inject a custom field into the login form HTML
 *   - #[http_post]       — Intercept login form submissions
 *   - #[on_method_exit]  — Hook after the session is created on successful login
 *
 * This demonstrates a real-world use case: adding a custom "Login Reason"
 * field to the login page that is logged for audit compliance.
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - Fusor installed at app/fusor/
 *   - uopz extension (for the on_method_exit hook only)
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * THE LOGIN FLOW IN FUSIONPBX:
 *   1. GET /resources/login.php     → renders the login form
 *   2. POST /resources/login.php    → submits credentials
 *   3. authentication::validate()   → checks username/password
 *   4. authentication::create_user_session() → creates session on success
 *   5. redirect to dashboard
 *
 * FUSOR HOOKS AVAILABLE FOR EACH STEP:
 *   Step 1: #[http_get('/resources/login.php', stage: 'before')]  — before form renders
 *   Step 1: #[http_get('/resources/login.php', stage: 'after')]   — after form HTML generated
 *   Step 2: #[http_post('/resources/login.php', stage: 'before')] — before auth processes POST
 *   Step 2: #[http_post('/resources/login.php', stage: 'after')]  — after auth processes POST
 *   Step 4: #[on_method_exit(target: 'authentication::create_user_session')] — after session
 *
 * @see app/fusor/resources/attributes/http_get.php
 * @see app/fusor/resources/attributes/http_post.php
 * @see app/fusor/resources/attributes/on_method_exit.php
 */

use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\attributes\http_post;
use Frytimo\Fusor\resources\attributes\on_method_exit;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_12_login_hooks {

	private const FIELD_NAME = 'login_reason';
	private static bool $field_injected = false;

	/**
	 * STEP 1: Inject a custom field into the login form.
	 *
	 * This 'before' GET hook registers an output buffer callback that
	 * modifies the login form HTML before it reaches the browser.
	 *
	 * The output buffer captures all HTML output from login.php, then
	 * the callback inserts our custom field after the password input.
	 */
	#[http_get(path: '/resources/login.php', stage: 'before')]
	public static function inject_login_field(fusor_event $event): void {
		if (self::$field_injected) {
			return;
		}
		self::$field_injected = true;

		ob_start(static function (string $html): string {
			// Don't inject if the field already exists (prevent duplicates)
			if (stripos($html, "name='" . self::FIELD_NAME . "'") !== false) {
				return $html;
			}

			$field_html = "\n\t\t\t<div class='form-group'>\n"
				. "\t\t\t\t<label for='" . self::FIELD_NAME . "'>Login Reason</label>\n"
				. "\t\t\t\t<input type='text' class='form-control' name='" . self::FIELD_NAME . "' "
				. "id='" . self::FIELD_NAME . "' placeholder='Optional: reason for login' />\n"
				. "\t\t\t</div>\n";

			// Insert after the password field
			$pattern = '/(<input[^>]+name=["\']password["\'][^>]*>)/i';
			$updated = preg_replace($pattern, '$1' . $field_html, $html, 1);

			return is_string($updated) ? $updated : $html;
		});
	}

	/**
	 * STEP 2: Capture the login reason from the POST submission.
	 *
	 * This 'before' POST hook runs before authentication processes the
	 * credentials. We capture the custom field value for later logging.
	 */
	#[http_post(path: '/resources/login.php', stage: 'before')]
	public static function capture_login_reason(fusor_event $event): void {
		$reason = '';
		if (isset($event->body[self::FIELD_NAME]) && is_string($event->body[self::FIELD_NAME])) {
			$reason = trim($event->body[self::FIELD_NAME]);
		}

		$username = '';
		if (isset($event->body['username']) && is_string($event->body['username'])) {
			$username = trim($event->body['username']);
		}

		if ($username !== '') {
			$remote_ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
			openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
			syslog(LOG_INFO, '[example_12] Login attempt — user: ' . $username . ' ip: ' . $remote_ip . ' reason: "' . $reason . '"');
			closelog();
		}
	}

	/**
	 * STEP 3: Check the login result after POST processing.
	 *
	 * This 'after' POST hook fires after authentication has completed.
	 * The session state tells us whether login succeeded or failed.
	 */
	#[http_post(path: '/resources/login.php', stage: 'after')]
	public static function log_login_result(fusor_event $event): void {
		$authorized = !empty($_SESSION['authorized']);
		$username = '';
		if (isset($event->body['username']) && is_string($event->body['username'])) {
			$username = trim($event->body['username']);
		}

		if ($username === '') {
			return;
		}

		$reason = '';
		if (isset($event->body[self::FIELD_NAME]) && is_string($event->body[self::FIELD_NAME])) {
			$reason = trim($event->body[self::FIELD_NAME]);
		}

		$status = $authorized ? 'SUCCESS' : 'FAILURE';
		openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
		syslog(
			$authorized ? LOG_INFO : LOG_WARNING,
			'[example_12] Login ' . $status . ' — user: ' . $username . ' reason: "' . $reason . '"'
		);
		closelog();
	}

	/**
	 * STEP 4: Hook after the user session is created (requires uopz).
	 *
	 * This on_method_exit hook fires after authentication::create_user_session()
	 * has been called. At this point the user is fully authenticated with
	 * session data populated.
	 *
	 * NOTE: This hook requires the uopz extension. If uopz is not available,
	 * this hook is silently skipped. The http_post hooks above (Steps 2-3)
	 * work without uopz and provide similar logging.
	 */
	#[on_method_exit(target: 'authentication::create_user_session')]
	public static function after_session_created(fusor_event $event): void {
		$user_uuid = $_SESSION['user_uuid'] ?? 'unknown';
		$domain = $_SESSION['domain_name'] ?? 'unknown';

		openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
		syslog(LOG_INFO, '[example_12] Session created — user_uuid: ' . $user_uuid . ' domain: ' . $domain);
		closelog();
	}
}
