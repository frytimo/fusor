<?php

/**
 * Example 17: Multi-Hook Class — Multiple Attributes on One Class
 *
 * A single class can combine many different Fusor attributes to provide
 * a comprehensive set of hooks across the entire FusionPBX application.
 * This is the recommended pattern for organizing related hooks.
 *
 * This example hooks into:
 *   - Login and logout flows (HTTP hooks)
 *   - Extension CRUD operations (uopz hooks)
 *   - Page rendering (event hooks)
 *   - Gateway management (uopz hooks)
 *   - Custom utility functions (runtime functions)
 *
 * REQUIREMENTS:
 *   - PHP 8.2+
 *   - uopz extension (for on_method_* and runtime_function hooks)
 *
 * INSTALLATION:
 *   1. Copy this file to: app/my_app/resources/classes/
 *   2. Run: /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
 *   3. Restart PHP-FPM: systemctl restart php8.4-fpm
 *
 * @see All attribute files in app/fusor/resources/attributes/
 */

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\attributes\http_post;
use Frytimo\Fusor\resources\attributes\on_method_enter;
use Frytimo\Fusor\resources\attributes\on_method_exit;
use Frytimo\Fusor\resources\attributes\runtime_function;
use Frytimo\Fusor\resources\classes\fusor_event;

class example_17_multi_hook_class {

	// ─── HTTP LIFECYCLE HOOKS ─────────────────────────────────────

	/**
	 * Log every page view with the user's session info.
	 */
	#[http_get(path: '*', stage: 'before', priority: -999)]
	public static function global_page_view_logger(fusor_event $event): void {
		// Skip if no session (user not authenticated)
		if (empty($_SESSION['username'])) {
			return;
		}

		// Only log when debug is enabled to avoid performance impact
		if (empty($_REQUEST['debug'])) {
			return;
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_DEBUG, '[example_17] Page view: ' . ($event->path ?? '?') . ' user: ' . ($_SESSION['username'] ?? '?'));
		closelog();
	}

	/**
	 * Intercept the ring groups edit page to log changes.
	 */
	#[http_post(path: '/app/ring_groups/ring_group_edit.php', stage: 'before')]
	public static function before_ring_group_save(fusor_event $event): void {
		$name = '';
		if (isset($event->body['ring_group_name']) && is_string($event->body['ring_group_name'])) {
			$name = trim($event->body['ring_group_name']);
		}

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_17] Ring group save: ' . $name);
		closelog();
	}

	// ─── PAGE RENDER HOOKS ────────────────────────────────────────

	/**
	 * Add a version footer to the extensions page.
	 */
	#[on(event_name: 'after_render_extensions', priority: -10)]
	public static function extensions_footer(fusor_event $event): void {
		if (!isset($event->data['html']) || !is_string($event->data['html'])) {
			return;
		}

		$footer = '<div style="text-align:center;padding:4px;font-size:11px;color:#888;">'
			. 'Fusor hooks active — ' . date('Y-m-d')
			. '</div>';

		$event->data['html'] = str_replace('</body>', $footer . '</body>', $event->data['html']);
	}

	// ─── UOPZ METHOD HOOKS ───────────────────────────────────────

	/**
	 * Audit log for extension deletion.
	 */
	#[on_method_enter(target: 'extensions::delete')]
	public static function before_extension_delete(fusor_event $event): void {
		$args = $event->arguments ?? [];
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_WARNING, '[example_17] AUDIT: extensions::delete with ' . count($args) . ' arg(s)');
		closelog();
	}

	/**
	 * Audit log after extension toggle.
	 */
	#[on_method_exit(target: 'extensions::toggle')]
	public static function after_extension_toggle(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_17] extensions::toggle completed');
		closelog();
	}

	/**
	 * Audit log for gateway operations.
	 */
	#[on_method_enter(target: 'gateways::copy')]
	public static function before_gateway_copy(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[example_17] gateways::copy entered');
		closelog();
	}

	#[on_method_enter(target: 'gateways::delete')]
	public static function before_gateway_delete(fusor_event $event): void {
		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_WARNING, '[example_17] AUDIT: gateways::delete entered');
		closelog();
	}

	// ─── RUNTIME FUNCTIONS ────────────────────────────────────────

	/**
	 * Provide a global audit logging helper.
	 *
	 * After registration, callable anywhere:
	 *   fusor_audit_log('extension', 'delete', 'ext-uuid-here');
	 */
	#[runtime_function(target: 'fusor_audit_log', action: 'add')]
	public static function fusor_audit_log(string $entity_type, string $action, string $entity_id = ''): void {
		$user = $_SESSION['username'] ?? 'system';
		$domain = $_SESSION['domain_name'] ?? 'global';

		openlog('FusionPBX', LOG_NDELAY | LOG_PID, LOG_USER);
		syslog(LOG_INFO, '[fusor_audit] ' . $action . ' ' . $entity_type . ' id=' . $entity_id . ' user=' . $user . ' domain=' . $domain);
		closelog();
	}
}
