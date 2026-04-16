<?php

declare(strict_types=1);

namespace Frytimo\Fusor\resources\classes;

use League\Uri\Uri;

/**
 * Shared HTTP request URL adapter for Fusor.
 *
 * This wrapper uses League URI for parsing while preserving the dual
 * filtered and unsafe parameter model used by FusionPBX applications.
 */
class http_request_url {
	public const FILTERED = 0;
	public const UNSAFE = 1;

	private const SOURCE_POST = 'post';
	private const SOURCE_INPUT = 'input';
	private const SOURCE_REQUEST = 'request';

	private Uri $uri;
	private array $params = [];
	private array $request_params = [];
	private string $original_url = '';
	private array $filter_chain = [];

	/**
	 * Construct.
	 * @param string|null $url
	 * @return mixed
	 */
	public function __construct(?string $url = null) {
		$this->original_url = (string) ($url ?? '');
		$this->uri = self::parse_uri($this->original_url);
		$this->set_query((string) $this->uri->getQuery());
	}

	/**
	 * Build an adapter from the active request globals.
	 * @return static
	 */
	public static function from_request(): static {
		$url = new static((string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '/'));
		$url->load_post(is_array($_POST ?? null) ? $_POST : []);
		$url->load_input();
		$url->load_request(is_array($_REQUEST ?? null) ? $_REQUEST : []);

		return $url;
	}

	/**
	 * Parse a URI string using League URI, falling back to a minimal path URI.
	 * @param string $url
	 * @return Uri
	 */
	private static function parse_uri(string $url): Uri {
		try {
			return Uri::new($url === '' ? '/' : $url);
		} catch (\Throwable $exception) {
			$path = self::normalize_path((string) (parse_url($url, PHP_URL_PATH) ?? '/'));
			$query = parse_url($url, PHP_URL_QUERY);
			$fragment = parse_url($url, PHP_URL_FRAGMENT);

			return Uri::fromComponents([
				'path' => $path === '' ? '/' : $path,
				'query' => is_string($query) && $query !== '' ? $query : null,
				'fragment' => is_string($fragment) && $fragment !== '' ? $fragment : null,
			]);
		}
	}

	/**
	 * Normalize request path values consistently across HTTP attributes and dispatch.
	 * @param string $path
	 * @return string
	 */
	public static function normalize_path(string $path): string {
		$path = trim($path);
		if ($path === '') {
			return '';
		}

		$parsed_path = parse_url($path, PHP_URL_PATH);
		if (is_string($parsed_path) && $parsed_path !== '') {
			$path = $parsed_path;
		}

		$path = '/' . ltrim($path, '/');
		$path = preg_replace('#/+#', '/', $path) ?? $path;

		if ($path !== '/' && str_ends_with($path, '/')) {
			$path = rtrim($path, '/');
		}

		return $path;
	}

	/**
	 * Returns the original input URL string.
	 * @return string
	 */
	public function get_original_url(): string {
		return $this->original_url;
	}

	/**
	 * Get the URI scheme.
	 * @return string
	 */
	public function get_scheme(): string {
		return (string) $this->uri->getScheme();
	}

	/**
	 * Get the URI host.
	 * @return string
	 */
	public function get_host(): string {
		return (string) $this->uri->getHost();
	}

	/**
	 * Get the URI port.
	 * @return string
	 */
	public function get_port(): string {
		$port = $this->uri->getPort();
		return $port === null ? '' : (string) $port;
	}

	/**
	 * Get the normalized path.
	 * @return string
	 */
	public function get_path(): string {
		return self::normalize_path((string) $this->uri->getPath());
	}

	/**
	 * Get the URI fragment.
	 * @return string
	 */
	public function get_fragment(): string {
		return (string) $this->uri->getFragment();
	}

	/**
	 * Replace the current query string.
	 * @param string $query
	 * @return static
	 */
	public function set_query(string $query = ''): static {
		$this->params = [];
		if ($query === '') {
			return $this;
		}

		$decoded = [];
		parse_str($query, $decoded);
		foreach ($decoded as $key => $value) {
			$this->set_query_param((string) $key, $value);
		}

		return $this;
	}

	/**
	 * Set a query parameter with both filtered and unsafe copies.
	 * @param string $key
	 * @param mixed $value
	 * @return static
	 */
	public function set_query_param(string $key, mixed $value): static {
		$key = strtolower(trim($key));
		if ($key === '') {
			throw new \InvalidArgumentException('Key must not be empty');
		}

		$this->params[$key][self::UNSAFE] = $value;
		$filtered = $this->filter_query_modifier($key, $value);
		if ($filtered !== null) {
			$this->params[$key][self::FILTERED] = $filtered;
		}

		return $this;
	}

	/**
	 * Alias of set_query_param.
	 * @param string $key
	 * @param mixed $value
	 * @return static
	 */
	public function set(string $key, mixed $value): static {
		return $this->set_query_param($key, $value);
	}

	/**
	 * Remove a query parameter.
	 * @param string $key
	 * @return static
	 */
	public function unset_query_param(string $key): static {
		unset($this->params[strtolower($key)]);

		return $this;
	}

	/**
	 * Alias of unset_query_param.
	 * @param string $key
	 * @return static
	 */
	public function delete(string $key): static {
		return $this->unset_query_param($key);
	}

	/**
	 * Return the encoded query string.
	 * @param int $unsafe
	 * @return string
	 */
	public function get_query(int $unsafe = self::FILTERED): string {
		return http_build_query($this->get_query_array($unsafe === self::UNSAFE), '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Return the filtered or raw query parameters.
	 * @param bool $unsafe
	 * @return array
	 */
	public function get_query_array(bool $unsafe = false): array {
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;
		$output = [];
		foreach ($this->params as $key => $values) {
			if (array_key_exists($slot, $values)) {
				$output[$key] = $values[$slot];
			}
		}

		return $output;
	}

	/**
	 * Get a single query parameter.
	 * @param string $key
	 * @param mixed $default
	 * @param bool $unsafe
	 * @return mixed
	 */
	public function get_query_param(string $key, mixed $default = null, bool $unsafe = false): mixed {
		$key = strtolower($key);
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;

		return $this->params[$key][$slot] ?? $default;
	}

	/**
	 * Returns true when one or more query parameters exist.
	 * @return bool
	 */
	public function has_parameters(): bool {
		return !empty($this->params);
	}

	/**
	 * Returns true when the query parameter exists.
	 * @param string $key
	 * @return bool
	 */
	public function has_query_param(string $key): bool {
		return isset($this->params[strtolower($key)]);
	}

	/**
	 * Returns a unified parameter value, checking query first then request body.
	 * @param string $key
	 * @param mixed $default
	 * @param bool $unsafe
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null, bool $unsafe = false): mixed {
		$key = strtolower($key);
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;

		if (isset($this->params[$key][$slot])) {
			return $this->params[$key][$slot];
		}

		return $this->request_params[$key][$slot] ?? $default;
	}

	/**
	 * Alias of get().
	 * @param string $key
	 * @param mixed $default
	 * @param bool $unsafe
	 * @return mixed
	 */
	public function request(string $key, mixed $default = null, bool $unsafe = false): mixed {
		return $this->get($key, $default, $unsafe);
	}

	/**
	 * Returns true when the key exists in either the query string or request body.
	 * @param string $key
	 * @return bool
	 */
	public function has(string $key): bool {
		$key = strtolower($key);
		return $this->has_query_param($key) || isset($this->request_params[$key]);
	}

	/**
	 * Load POST form values.
	 * @param array $post
	 * @return static
	 */
	public function load_post(array $post): static {
		$this->import_request_params($post, self::SOURCE_POST, true);
		return $this;
	}

	/**
	 * Get a POST parameter.
	 * @param string $key
	 * @param mixed $default
	 * @param bool $unsafe
	 * @return mixed
	 */
	public function post(string $key, mixed $default = null, bool $unsafe = false): mixed {
		$key = strtolower($key);
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;
		if (isset($this->request_params[$key]) && $this->request_params[$key]['source'] === self::SOURCE_POST) {
			return $this->request_params[$key][$slot] ?? $default;
		}

		return $default;
	}

	/**
	 * Returns true when the POST parameter exists.
	 * @param string $key
	 * @return bool
	 */
	public function has_post(string $key): bool {
		$key = strtolower($key);
		return isset($this->request_params[$key]) && $this->request_params[$key]['source'] === self::SOURCE_POST;
	}

	/**
	 * Return all POST parameters.
	 * @param bool $unsafe
	 * @return array
	 */
	public function get_post_array(bool $unsafe = false): array {
		return $this->get_request_array_for_source(self::SOURCE_POST, $unsafe);
	}

	/**
	 * Return all php://input parameters.
	 * @param bool $unsafe
	 * @return array
	 */
	public function get_input_array(bool $unsafe = false): array {
		return $this->get_request_array_for_source(self::SOURCE_INPUT, $unsafe);
	}

	/**
	 * Return the merged inbound request parameter store.
	 * @param bool $unsafe
	 * @return array
	 */
	public function get_request_array(bool $unsafe = false): array {
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;
		$output = [];
		foreach ($this->request_params as $key => $values) {
			if (array_key_exists($slot, $values)) {
				$output[$key] = $values[$slot];
			}
		}

		return $output;
	}

	/**
	 * Return only the inbound body values from POST and php://input sources.
	 * @param bool $unsafe
	 * @return array
	 */
	public function get_body_array(bool $unsafe = false): array {
		return array_replace(
			$this->get_input_array($unsafe),
			$this->get_post_array($unsafe),
		);
	}

	/**
	 * Load php://input values when present.
	 * @return static
	 */
	public function load_input(): static {
		$raw = file_get_contents('php://input');
		if ($raw === false || $raw === '') {
			return $this;
		}

		$content_type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
		if (str_contains($content_type, 'application/json')) {
			$data = json_decode($raw, true);
			if (is_array($data)) {
				$this->import_request_params($data, self::SOURCE_INPUT, false);
			}
		} else {
			$data = [];
			parse_str($raw, $data);
			$this->import_request_params($data, self::SOURCE_INPUT, false);
		}

		return $this;
	}

	/**
	 * Get an input body parameter.
	 * @param string $key
	 * @param mixed $default
	 * @param bool $unsafe
	 * @return mixed
	 */
	public function input(string $key, mixed $default = null, bool $unsafe = false): mixed {
		$key = strtolower($key);
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;
		if (isset($this->request_params[$key]) && $this->request_params[$key]['source'] === self::SOURCE_INPUT) {
			return $this->request_params[$key][$slot] ?? $default;
		}

		return $default;
	}

	/**
	 * Returns true when the input parameter exists.
	 * @param string $key
	 * @return bool
	 */
	public function has_input(string $key): bool {
		$key = strtolower($key);
		return isset($this->request_params[$key]) && $this->request_params[$key]['source'] === self::SOURCE_INPUT;
	}

	/**
	 * Load request values as a fallback source.
	 * @param array $request
	 * @return static
	 */
	public function load_request(array $request): static {
		$this->import_request_params($request, self::SOURCE_REQUEST, false);
		return $this;
	}

	/**
	 * Add a custom query filter to the adapter pipeline.
	 * @param callable $filter
	 * @return static
	 */
	public function add_query_filter(callable $filter): static {
		$this->filter_chain[] = $filter;
		return $this;
	}

	/**
	 * Build the relative request URI.
	 * @param int $unsafe
	 * @return string
	 */
	public function build_relative(int $unsafe = self::FILTERED): string {
		$uri = $this->get_path();
		$query = $this->get_query($unsafe);
		if ($query !== '') {
			$uri .= '?' . $query;
		}

		$fragment = $this->get_fragment();
		if ($fragment !== '') {
			$uri .= '#' . $fragment;
		}

		return $uri;
	}

	/**
	 * Build the full URI string if authority information is available.
	 * @param int $unsafe
	 * @return string
	 */
	public function build_absolute(int $unsafe = self::FILTERED): string {
		$uri = $this->uri
			->withPath($this->get_path())
			->withQuery($this->get_query($unsafe))
			->withFragment($this->get_fragment());

		return $uri->toString();
	}

	/**
	 * String cast helper.
	 * @return string
	 */
	public function __toString(): string {
		return $this->build_relative();
	}

	/**
	 * Import request data into the internal source-aware store.
	 * @param array $data
	 * @param string $source
	 * @param bool $overwrite
	 * @return void
	 */
	private function import_request_params(array $data, string $source, bool $overwrite = false): void {
		foreach ($data as $key => $value) {
			$key = strtolower(trim((string) $key));
			if ($key === '') {
				continue;
			}

			if (!$overwrite && isset($this->request_params[$key])) {
				continue;
			}

			$this->request_params[$key] = [
				self::UNSAFE => $value,
				self::FILTERED => $this->sanitize_value($value),
				'source' => $source,
			];
		}
	}

	/**
	 * Apply the default sanitizer and optional filter chain.
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	protected function filter_query_modifier(string $key, mixed $value): mixed {
		$filtered = $this->default_query_filter($key, $value);
		if ($filtered === null || empty($this->filter_chain)) {
			return $filtered;
		}

		$pipeline = fn(string $k, mixed $v) => $v;
		foreach (array_reverse($this->filter_chain) as $filter) {
			$pipeline = fn(string $k, mixed $v) => $filter($k, $v, $pipeline);
		}

		return $pipeline($key, $filtered);
	}

	/**
	 * Default sanitization rules for query parameters.
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	protected function default_query_filter(string $key, mixed $value): mixed {
		$filtered = $this->sanitize_value($value);

		if ($key === 'sort' && is_string($filtered) && !in_array($filtered, ['asc', 'dsc', 'natural'], true)) {
			return null;
		}

		if ($key === 'page' && !is_numeric($filtered)) {
			return null;
		}

		return $filtered;
	}

	/**
	 * Sanitize scalar or array values recursively.
	 * @param mixed $value
	 * @return mixed
	 */
	private function sanitize_value(mixed $value): mixed {
		if (is_array($value)) {
			$sanitized = [];
			foreach ($value as $nested_key => $nested_value) {
				$sanitized[$nested_key] = $this->sanitize_value($nested_value);
			}

			return $sanitized;
		}

		if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
			return $value;
		}

		if (is_object($value) && !method_exists($value, '__toString')) {
			return null;
		}

		return filter_var((string) $value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	}

	/**
	 * Return filtered or raw request values for a specific source.
	 * @param string $source
	 * @param bool $unsafe
	 * @return array
	 */
	private function get_request_array_for_source(string $source, bool $unsafe = false): array {
		$slot = $unsafe ? self::UNSAFE : self::FILTERED;
		$output = [];
		foreach ($this->request_params as $key => $values) {
			if (($values['source'] ?? '') !== $source) {
				continue;
			}

			if (array_key_exists($slot, $values)) {
				$output[$key] = $values[$slot];
			}
		}

		return $output;
	}
}
