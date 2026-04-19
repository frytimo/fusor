<?php

namespace Frytimo\Fusor\resources\classes;

/**
 * Http route hook dispatcher.
 */
class http_route_hook_dispatcher {
	private static string $last_request_fingerprint = '';

	/**
	 * Dispatch request hooks.
	 * @param mixed $autoload
	 * @param mixed $force_refresh
	 * @return int
	 */
	public static function dispatch_request_hooks(\auto_loader $autoload, bool $force_refresh = false): int {
		$request_fingerprint = self::resolve_request_fingerprint();
		if ($request_fingerprint !== '' && self::$last_request_fingerprint === $request_fingerprint) {
			return 0;
		}

		if ($request_fingerprint !== '') {
			self::$last_request_fingerprint = $request_fingerprint;
		}

		$request_method = strtolower(trim((string) ($_SERVER['REQUEST_METHOD'] ?? '')));
		if ($request_method !== 'get' && $request_method !== 'post') {
			return 0;
		}

		$request_url = http_request_url::from_request();
		$request_path = $request_url->get_path();
		if ($request_path === '') {
			return 0;
		}

		$supports_attribute_discovery = method_exists($autoload, 'get_attributes');
		if (!$supports_attribute_discovery) {
			throw new \RuntimeException('Fusor requires an auto_loader that supports attribute discovery for HTTP lifecycle hooks to function.');
		}

		fusor_discovery::discover_attributes(auto_loader: $autoload, force_refresh: false);

		$method_upper = strtoupper($request_method);
		$event_data = [
			'method' => $method_upper,
			'path' => $request_path,
			'params' => [],
			'query' => is_array($_GET) ? $_GET : [],
			'query_safe' => $request_url->get_query_array(),
			'body' => is_array($_POST) ? $_POST : [],
			'body_safe' => $request_url->get_body_array(),
			'url' => $request_url,
			'html' => '',
		];

		$before_events = [
			'before_http_' . $request_method,
			'before_http_' . $request_method . ':' . $request_path,
		];

		$after_events = [
			'after_http_' . $request_method,
			'after_http_' . $request_method . ':' . $request_path,
		];

		$invoked = 0;
		foreach ($before_events as $before_event_name) {
			if (!fusor_dispatcher::has_listeners($before_event_name)) {
				continue;
			}

			$event = new fusor_event($before_event_name, data: $event_data);
			fusor_dispatcher::dispatch($event);
			++$invoked;
		}

		$has_after_events = false;
		foreach ($after_events as $after_event_name) {
			if (fusor_dispatcher::has_listeners($after_event_name)) {
				$has_after_events = true;
				break;
			}
		}

		if ($has_after_events) {
			$fusor_buffer_base_level = ob_get_level();
			$fusor_buffer_target_level = $fusor_buffer_base_level + 1;
			ob_start();

			register_shutdown_function(static function () use ($after_events, $event_data, $fusor_buffer_base_level, $fusor_buffer_target_level): void {
				$output = '';
				while (ob_get_level() > $fusor_buffer_base_level) {
					if (ob_get_level() === $fusor_buffer_target_level) {
						$output = (string) ob_get_contents();
						ob_end_clean();
						continue;
					}

					ob_end_flush();
				}

				$html_output = $output;
				$after_event_data = $event_data;
				$after_event_data['html'] = &$html_output;

				foreach ($after_events as $after_event_name) {
					if (!fusor_dispatcher::has_listeners($after_event_name)) {
						continue;
					}

					$event = new fusor_event($after_event_name, data: $after_event_data);
					fusor_dispatcher::dispatch($event);
				}

				echo $html_output;
			});
			++$invoked;
		}

		return $invoked;
	}

	/**
	 * Resolve request fingerprint.
	 * @return string
	 */
	private static function resolve_request_fingerprint(): string {
		$request_time = (string) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? '');
		$request_method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
		$request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		$script_name = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

		$fingerprint = trim($request_time . '|' . $request_method . '|' . $request_uri . '|' . $script_name, '|');
		if ($fingerprint === '') {
			return '';
		}

		return sha1($fingerprint);
	}

	/**
	 * Resolve request path.
	 * @return string
	 */
	private static function resolve_request_path(): string {
		$request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		if ($request_uri !== '') {
			$parsed = parse_url($request_uri, PHP_URL_PATH);
			if (is_string($parsed)) {
				return self::normalize_path($parsed);
			}
		}

		$script_name = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
		return self::normalize_path($script_name);
	}

	/**
	 * Normalize path.
	 * @param mixed $path
	 * @return string
	 */
	private static function normalize_path(string $path): string {
		return http_request_url::normalize_path($path);
	}
}

