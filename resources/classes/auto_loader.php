<?php

/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2024
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * Auto Loader class
 * Searches for project files when a class is required. Debugging mode can be set using:
 * - export DEBUG=1
 *      OR
 * - debug=true is appended to the url
 */
class auto_loader {
	const CLASSES_KEY       = 'autoloader_classes';
	const CLASSES_FILE      = 'autoloader_cache.php';
	const INTERFACES_KEY    = "autoloader_interfaces";
	const INTERFACES_FILE   = "autoloader_interface_cache.php";
	const INHERITANCE_KEY   = "autoloader_inheritance";
	const INHERITANCE_FILE  = "autoloader_inheritance_cache.php";
	const ATTRIBUTES_KEY    = 'autoloader_attributes';
	const ATTRIBUTES_FILE   = 'autoloader_attributes_cache.php';
	const CACHE_VERSION_KEY = 'autoloader_cache_version';
	const CACHE_VERSION     = 7;

	/**
	 * Cache path and file name for classes
	 *
	 * @var string
	 */
	private static $classes_file = null;

	/**
	 * Cache path and file name for interfaces
	 *
	 * @var string
	 */
	private static $interfaces_file = null;

	/**
	 * Cache path and file name for inheritance
	 *
	 * @var string
	 */
	private static $inheritance_file = null;

	/**
	 * Cache path and file name for attributes
	 *
	 * @var string
	 */
	private static $attributes_file = null;

	private $classes;

	/**
	 * Tracks the APCu extension for caching to RAM drive across requests
	 *
	 * @var bool
	 */
	private $apcu_enabled;

	/**
	 * Maps interfaces to classes
	 *
	 * @var array
	 */
	private $interfaces;

	/**
	 * Maps classes/interfaces to their parent class/interface
	 *
	 * @var array
	 */
	private $inheritance;

	/**
	 * @var array
	 */
	private $traits;

	/**
	 * Maps declaration targets to discovered attributes.
	 *
	 * @var array
	 */
	private $attributes;

	/**
	 * Parsed settings from app/fusor/.env.
	 *
	 * @var array|null
	 */
	private $env_settings = null;

	private bool $cache_enabled = false;

	/**
	 * Tracks missing class warnings to avoid repeated per-request log spam.
	 *
	 * @var array<string,bool>
	 */
	private array $missing_class_warnings = [];

	/**
	 * Initializes the class and primes the APCu-backed cache when available.
	 *
	 * @param bool $cache If true, enables persistent APCu caching.
	 */
	public function __construct($cache = true) {
		openlog("PHP", LOG_PID | LOG_PERROR, LOG_LOCAL0);

		$this->cache_enabled = (bool) $cache;
		$this->apcu_enabled  = $this->cache_enabled && self::is_apcu_available();
		$this->classes       = [];
		$this->interfaces    = [];
		$this->inheritance   = [];
		$this->traits        = [];
		$this->attributes    = $this->default_attribute_map();

		if (!$this->load_cache()) {
			$this->info("No valid autoloader cache found. Building class map from resources.");
			$this->reload_classes();
			$this->rebuild_traits_from_classes();
			$this->update_cache();
		}

		$this->debug("Classes: " . implode(', ', array_keys($this->classes)));
		$this->debug("Attributes: " . implode(', ', array_keys($this->attributes['method'] ?? [])));
		$this->info("auto_loader initialized with " . count($this->classes) . " classes, " . count($this->interfaces) . " interfaces, " . count($this->traits) . " traits, and " . count($this->attributes) . " attributes.");
		spl_autoload_register([$this, 'loader']);
		if (self::is_log_level_enabled(LOG_DEBUG)) {
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
			$this->debug("Autoloader registered at " . ($backtrace[0]['file'] ?? 'unknown file') . ":" . ($backtrace[0]['line'] ?? 'unknown line'));
		}
	}

	public function __destruct() {
		try {
			spl_autoload_unregister([$this, 'loader']);
		} catch (\Throwable $t) {
			// ignore errors during shutdown
			$this->error("Error during auto_loader shutdown: " . $t->getMessage());
			// we can't do much about it at this point, so just log and move on
		} finally {
			@closelog();
		}
	}

	/**
	 * Logs a message at the specified level.
	 *
	 * When `log_file` is set in the environment the message is appended to that file
	 * in addition to being sent to syslog. The log file path is resolved once per
	 * process and cached in a static variable.
	 *
	 * @param int    $level   The log level (e.g. LOG_WARNING)
	 * @param string $message The log message
	 */
	private static function log(int $level, string $message): void {
		$formatted = "[auto_loader] " . $message;
		syslog($level, $formatted);

		$log_file = trim((string) ($_ENV['log_file'] ?? ''));
		if ($log_file === '') {
			return;
		}

		$level_label = match ($level) {
			LOG_DEBUG   => 'DEBUG',
			LOG_INFO    => 'INFO',
			LOG_NOTICE  => 'NOTICE',
			LOG_WARNING => 'WARNING',
			LOG_ERR     => 'ERROR',
			LOG_CRIT    => 'CRITICAL',
			default     => 'LOG',
		};

		$line = date('Y-m-d H:i:s') . " [{$level_label}] {$formatted}" . PHP_EOL;
		@file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Returns true when the provided log level should be emitted.
	 *
	 * @param int $level Syslog level constant
	 *
	 * @return bool
	 */
	private static function is_log_level_enabled(int $level): bool {
		$threshold = self::get_log_level_threshold();

		return $threshold !== null && $level <= $threshold;
	}

	/**
	 * Indicates whether APCu is available for persistent autoloader caching.
	 *
	 * @return bool
	 */
	private static function is_apcu_available(): bool {
		if (!function_exists('apcu_fetch') || !function_exists('apcu_store') || !function_exists('apcu_delete')) {
			return false;
		}

		if (function_exists('apcu_enabled')) {
			return apcu_enabled();
		}

		$apc_enabled     = filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
		$apc_cli_enabled = PHP_SAPI !== 'cli' || filter_var((string) ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN);

		self::notice("APCu availability: apc.enabled=" . ($apc_enabled ? 'true' : 'false') . ", apc.enable_cli=" . ($apc_cli_enabled ? 'true' : 'false'));

		return $apc_enabled && $apc_cli_enabled;
	}

	/**
	 * Loads the class metadata cache from APCu when available.
	 *
	 * @return bool True when a valid cache entry was loaded.
	 */
	public function load_cache(): bool {
		$this->classes     = [];
		$this->interfaces  = [];
		$this->inheritance = [];
		$this->traits      = [];
		$this->attributes  = $this->default_attribute_map();

		if (!$this->cache_enabled) {
			return false;
		}

		if (!$this->apcu_enabled) {
			return $this->load_file_cache();
		}

		$cache_version     = apcu_fetch(self::CACHE_VERSION_KEY, $version_cached);
		$this->classes     = apcu_fetch(self::CLASSES_KEY, $classes_cached);
		$this->interfaces  = apcu_fetch(self::INTERFACES_KEY, $interfaces_cached);
		$this->inheritance = apcu_fetch(self::INHERITANCE_KEY, $inheritance_cached);
		$this->attributes  = apcu_fetch(self::ATTRIBUTES_KEY, $attributes_cached);

		if (!$version_cached || !$classes_cached || !$interfaces_cached || !$inheritance_cached || !$attributes_cached) {
			$this->classes     = [];
			$this->interfaces  = [];
			$this->inheritance = [];
			$this->attributes  = $this->default_attribute_map();

			return false;
		}

		if ($cache_version !== self::CACHE_VERSION) {
			$this->notice("Autoloader APCu cache version mismatch. Rebuilding.");
			self::clear_cache();
			$this->classes     = [];
			$this->interfaces  = [];
			$this->inheritance = [];
			$this->attributes  = $this->default_attribute_map();

			return false;
		}

		if (!is_array($this->classes) || !is_array($this->interfaces) || !is_array($this->inheritance) || !is_array($this->attributes) || empty($this->classes)) {
			$this->warning("Autoloader APCu cache failed validation. Rebuilding.");
			self::clear_cache();
			$this->classes     = [];
			$this->interfaces  = [];
			$this->inheritance = [];
			$this->attributes  = $this->default_attribute_map();

			return false;
		}

		$this->attributes = array_replace($this->default_attribute_map(), $this->attributes);
		$this->rebuild_traits_from_classes();
		$this->debug("Autoloader APCu cache loaded: " . count($this->classes) . " classes, " . count($this->interfaces) . " interfaces, " . count($this->attributes['method'] ?? []) . " method attributes.");

		return true;
	}

	/**
	 * Loads class metadata cache from filesystem when APCu is unavailable.
	 *
	 * @return bool True when a valid cache entry was loaded.
	 */
	private function load_file_cache(): bool {
		$cache_file = self::get_file_cache_path(self::CLASSES_FILE);
		if ($cache_file === '' || !is_file($cache_file)) {
			return false;
		}

		$payload = @include $cache_file;
		if (!is_array($payload)) {
			return false;
		}

		$cache_version = $payload['version'] ?? null;
		$classes       = $payload['classes'] ?? null;
		$interfaces    = $payload['interfaces'] ?? null;
		$inheritance   = $payload['inheritance'] ?? null;
		$attributes    = $payload['attributes'] ?? null;

		if ($cache_version !== self::CACHE_VERSION) {
			$this->notice("Autoloader file cache version mismatch. Rebuilding.");
			self::clear_file_cache();

			return false;
		}

		if (!is_array($classes) || !is_array($interfaces) || !is_array($inheritance) || !is_array($attributes) || empty($classes)) {
			$this->warning("Autoloader file cache failed validation. Rebuilding.");
			self::clear_file_cache();

			return false;
		}

		$expire_secs = (int) ($_ENV['cache_expire_time'] ?? 0);
		if ($expire_secs > 0) {
			$written_at = (int) ($payload['written_at'] ?? 0);
			if ($written_at > 0 && (time() - $written_at) > $expire_secs) {
				$this->notice("Autoloader file cache expired (age " . (time() - $written_at) . "s > {$expire_secs}s). Rebuilding.");
				self::clear_file_cache();

				return false;
			}
		}

		$this->classes     = $classes;
		$this->interfaces  = $interfaces;
		$this->inheritance = $inheritance;
		$this->attributes  = array_replace($this->default_attribute_map(), $attributes);
		$this->rebuild_traits_from_classes();
		$this->debug("Autoloader file cache loaded from $cache_file: " . count($this->classes) . " classes, " . count($this->interfaces) . " interfaces, " . count($this->attributes['method'] ?? []) . " method attributes.");

		return true;
	}

	/**
	 * Reloads classes and interfaces from the project's resources.
	 *
	 * This method scans all PHP files in the specified locations, parses their contents,
	 * and updates the internal storage of classes and interfaces. It also processes
	 * implementation relationships between classes and interfaces.
	 *
	 * @param bool $include_attributes When false, attribute metadata parsing is skipped.
	 *
	 * @return void
	 */
	public function reload_classes(bool $include_attributes = true): void {
		// set project path using magic dir constant
		$project_path = defined('PROJECT_ROOT_DIR') ? PROJECT_ROOT_DIR : dirname(__DIR__, 4);
		$scan_paths   = $_ENV['scan_path'] ?? [];

		if (empty($scan_paths) || !is_array($scan_paths)) {
			$this->error("No scan paths defined for auto_loader. Check the environment settings.");

			return;
		}

		// get all php files for each path
		$files = [];
		foreach ($scan_paths as $path) {
			$files = array_merge($files, glob($project_path . $path));
		}

		// reset the current array
		$class_list   = [];
		$this->traits = [];
		if ($include_attributes) {
			$this->attributes = $this->default_attribute_map();
		}

		// store the class name (key) and the path (value)
		foreach ($files as $file) {
			// index attributes declared on classes, methods, properties, and constants
			if ($include_attributes) {
				$this->parse_attribute_file($file);
			}

			$file_content = file_get_contents($file);

			// Remove block comments
			$file_content = preg_replace('/\/\*.*?\*\//s', '', $file_content);
			// Remove single-line comments
			$file_content = preg_replace('/(\/\/|#).*$/m', '', $file_content);

			// Detect the namespace
			$namespace = '';
			if (preg_match('/\bnamespace\s+([^;{]+)[;{]/', $file_content, $namespace_match)) {
				$namespace = trim($namespace_match[1]) . '\\';
			}

			// Regex to capture class, interface, or trait declarations
			// Now captures the extends clause properly as $match[3]
			$pattern = '/\b(class|interface|trait)\s+(\w+)(?:\s+extends\s+(\w+))?(?:\s+implements\s+([^\\{]+))?/';

			if (preg_match_all($pattern, $file_content, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					// "class", "interface", or "trait"
					$type = $match[1];

					// The class/interface/trait name
					$name = trim($match[2], " \n\r\t\v\x00\\");

					// Combine the namespace and name
					$full_name       = $namespace . $name;
					$lower_full_name = strtolower($full_name);

					// Store the class/interface/trait with its file overwriting any existing declaration.
					$this->classes[$full_name]       = $file;
					$this->classes[$lower_full_name] = $file;
					if ($type === 'trait') {
						$this->traits[$full_name]       = $file;
						$this->traits[$lower_full_name] = $file;
					}

					// Track inheritance (what this class/interface extends)
					if (isset($match[3]) && trim($match[3]) !== '') {
						$parent_name                         = trim($match[3], " \n\r\t\v\x00\\");
						$this->inheritance[$full_name]       = $parent_name;
						$this->inheritance[$lower_full_name] = $parent_name;
					}

					// If it's a class that implements interfaces, process the implements clause.
					if ($type === 'class' && isset($match[4]) && trim($match[4]) !== '') {
						// Split the interface list by commas.
						$interface_list = explode(',', $match[4]);
						foreach ($interface_list as $interface) {
							$interface_name       = trim($interface, " \n\r\t\v\x00\\");
							$lower_interface_name = strtolower($interface_name);
							// Check that it is declared as an array so we can record the classes
							if (empty($this->interfaces[$interface_name])) {
								$this->interfaces[$interface_name] = [];
							}
							if (empty($this->interfaces[$lower_interface_name])) {
								$this->interfaces[$lower_interface_name] = [];
							}

							// Ensure we don't already have the class recorded
							if (!in_array($full_name, $this->interfaces[$interface_name], true)) {
								// Record the classes that implement interface sorting by namspace and class name
								$this->interfaces[$interface_name][] = $full_name;
							}
							if (!in_array($full_name, $this->interfaces[$lower_interface_name], true)) {
								$this->interfaces[$lower_interface_name][] = $full_name;
							}
						}
					}
				}
			} else {
				//
				// When the file is in the classes|interfaces|traits folder then
				// we must assume it is a valid class as IonCube will encode the
				// class name. So, we use the file name as the class name in the
				// global  namespace and  set it,  checking first  to ensure the
				// basename does not  override an already declared class file in
				// order to mimic previous behaviour.
				//

				// use the basename as the class name
				$class_name = basename($file, '.php');
				if (!isset($this->classes[$class_name])) {
					$this->classes[$class_name] = $file;
				}
				if (strpos(str_replace('\\', '/', $file), '/resources/traits/') !== false) {
					$this->traits[$class_name] = $file;
				}
			}
		}
	}

	/**
	 * Rebuilds the trait index from the discovered class map.
	 *
	 * @return void
	 */
	private function rebuild_traits_from_classes(): void {
		$this->traits = [];

		foreach ($this->classes as $name => $path) {
			if (!is_string($path)) {
				continue;
			}

			if (strpos(str_replace('\\', '/', $path), '/resources/traits/') !== false) {
				$this->traits[$name] = $path;
			}
		}
	}

	/**
	 * Updates the APCu cache with the current autoloader maps.
	 *
	 * @return bool True when the cache state is valid for the current request.
	 */
	public function update_cache(): bool {
		if (empty($this->classes)) {
			return false;
		}

		if (!$this->cache_enabled) {
			return true;
		}

		if (!$this->apcu_enabled) {
			return $this->update_file_cache();
		}

		$expire_secs = (int) ($_ENV['cache_expire_time'] ?? 0);
		$ttl         = $expire_secs > 0 ? $expire_secs : 0;

		$result = apcu_store([
			self::CACHE_VERSION_KEY => self::CACHE_VERSION,
			self::CLASSES_KEY       => $this->classes,
			self::INTERFACES_KEY    => $this->interfaces,
			self::INHERITANCE_KEY   => $this->inheritance,
			self::ATTRIBUTES_KEY    => $this->attributes,
		], null, $ttl);

		if ($result === true || (is_array($result) && empty($result))) {
			$this->debug("Autoloader APCu cache written: " . count($this->classes) . " classes, " . count($this->interfaces) . " interfaces, " . count($this->attributes['method'] ?? []) . " method attributes" . ($ttl > 0 ? ", TTL={$ttl}s" : ", no TTL") . ".");
			return true;
		}

		$this->warning("Failed to persist the autoloader map to APCu.");
		self::clear_cache();

		return false;
	}

	/**
	 * Updates filesystem cache when APCu is unavailable.
	 *
	 * @return bool
	 */
	private function update_file_cache(): bool {
		$cache_file = self::get_file_cache_path(self::CLASSES_FILE);
		if ($cache_file === '') {
			return true;
		}

		$cache_dir = dirname($cache_file);
		if (!is_dir($cache_dir) && !@mkdir($cache_dir, 0775, true) && !is_dir($cache_dir)) {
			$this->warning("Failed to create autoloader cache directory at $cache_dir.");

			return false;
		}

		$payload = [
			'version'     => self::CACHE_VERSION,
			'written_at'  => time(),
			'classes'     => $this->classes,
			'interfaces'  => $this->interfaces,
			'inheritance' => $this->inheritance,
			'attributes'  => $this->attributes,
		];

		$php_cache = "<?php\nreturn " . var_export($payload, true) . ";\n";
		$temp_file = $cache_file . '.tmp';
		if (@file_put_contents($temp_file, $php_cache, LOCK_EX) === false) {
			$this->warning("Failed to write temporary autoloader file cache to $temp_file. Check that the directory is writable by the PHP process user.");
			@unlink($temp_file);

			return false;
		}

		if (!@rename($temp_file, $cache_file)) {
			$this->warning("Failed to finalize autoloader file cache write.");
			@unlink($temp_file);

			return false;
		}

		$this->debug("Autoloader file cache written to $cache_file: " . count($this->classes) . " classes, " . count($this->interfaces) . " interfaces, " . count($this->attributes['method'] ?? []) . " method attributes.");

		return true;
	}

	/**
	 * Returns the configured log level threshold, or null when logging is disabled.
	 *
	 * Supported values for $_ENV['debug_level']:
	 *   false / 0 / ''  → disabled
	 *   true  / 1       → debug (most verbose, same as 'debug')
	 *   debug           → LOG_DEBUG  (7) — show all messages
	 *   info            → LOG_INFO   (6) — show info, notice, warning, error
	 *   notice          → LOG_NOTICE (5) — show notice, warning, error
	 *   warning         → LOG_WARNING(4) — show warning, error
	 *   error           → LOG_ERR    (3) — show errors only
	 *
	 * @return int|null
	 */
	private static function get_log_level_threshold(): ?int {
		static $threshold_cache = 'unset';
		if ($threshold_cache !== 'unset') {
			return $threshold_cache;
		}

		$setting = strtolower(trim((string) ($_ENV['debug_level'] ?? '')));

		$threshold_cache = match ($setting) {
			'', 'false', '0' => null,
			'true', '1'      => LOG_DEBUG,
			'debug'          => LOG_DEBUG,
			'info'           => LOG_INFO,
			'notice'         => LOG_NOTICE,
			'warning'        => LOG_WARNING,
			'error'          => LOG_ERR,
			default          => null,
		};

		return $threshold_cache;
	}

	public static function debug(string $message): void {
		if (self::is_log_level_enabled(LOG_DEBUG)) {
			self::log(LOG_DEBUG, $message);
		}
	}

	public static function info(string $message): void {
		if (self::is_log_level_enabled(LOG_INFO)) {
			self::log(LOG_INFO, $message);
		}
	}

	public static function error(string $message): void {
		if (self::is_log_level_enabled(LOG_ERR)) {
			self::log(LOG_ERR, $message);
		}
	}

	public static function warning(string $message): void {
		if (self::is_log_level_enabled(LOG_WARNING)) {
			self::log(LOG_WARNING, $message);
		}
	}

	public static function notice(string $message): void {
		if (self::is_log_level_enabled(LOG_NOTICE)) {
			self::log(LOG_NOTICE, $message);
		}
	}

	/**
	 * Rebuilds all cached maps (classes, interfaces, inheritance, attributes) and flushes the cache.
	 *
	 * @return void
	 */
	public function update(): void {
		self::clear_cache();
		$this->reload_classes();
		$this->rebuild_traits_from_classes();
		$this->update_cache();
	}

	/**
	 * Rebuilds only the class, interface, and inheritance maps without touching attribute metadata.
	 *
	 * @return void
	 */
	public function rebuild_classes_cache(): void {
		$this->classes     = [];
		$this->interfaces  = [];
		$this->inheritance = [];
		$this->traits      = [];
		$this->reload_classes(false);
		$this->rebuild_traits_from_classes();
		$this->update_cache();
	}

	/**
	 * Rebuilds only attribute metadata by re-scanning all files, leaving class/interface/inheritance maps intact.
	 *
	 * @param bool $methods_only When true only the method attribute sub-map is rebuilt, leaving all other
	 *                           attribute targets (class, property, constant, etc.) intact.
	 *
	 * @return void
	 */
	public function rebuild_attributes_cache(bool $methods_only = false): void {
		$project_path = defined('PROJECT_ROOT_DIR') ? PROJECT_ROOT_DIR : dirname(__DIR__, 4);
		$scan_paths   = $_ENV['scan_path'] ?? [];

		if (empty($scan_paths) || !is_array($scan_paths)) {
			$this->error("No scan paths defined for auto_loader. Check the environment settings.");

			return;
		}

		$files = [];
		foreach ($scan_paths as $path) {
			$files = array_merge($files, glob($project_path . $path));
		}

		if ($methods_only) {
			$this->attributes['method'] = [];
		} else {
			$this->attributes = $this->default_attribute_map();
		}

		foreach (array_unique($files) as $file) {
			$this->parse_attribute_file($file);
		}

		$this->update_cache();
	}

	/**
	 * Returns true when the file cache is older than the configured cache_expire_time threshold.
	 * Always returns false when cache_expire_time is 0 (disabled).
	 *
	 * @return bool
	 */
	public function is_cache_expired(): bool {
		$expire_secs = (int) ($_ENV['cache_expire_time'] ?? 0);
		if ($expire_secs <= 0) {
			return false;
		}

		$cache_file = self::get_file_cache_path(self::CLASSES_FILE);
		if ($cache_file === '' || !is_file($cache_file)) {
			// No file cache — APCu handles its own TTL; treat as not expired
			return false;
		}

		$mtime = @filemtime($cache_file);
		if ($mtime === false) {
			return true;
		}

		return (time() - $mtime) > $expire_secs;
	}

	/**
	 * Clears the APCu-backed autoloader cache.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		if (self::is_apcu_available()) {
			apcu_delete([
				self::CACHE_VERSION_KEY,
				self::CLASSES_KEY,
				self::INTERFACES_KEY,
				self::INHERITANCE_KEY,
				self::ATTRIBUTES_KEY,
			]);
			self::notice("Autoloader APCu cache cleared.");
		} else {
			self::notice("Autoloader APCu cache clear skipped because APCu is unavailable.");
		}

		self::clear_file_cache();
	}

	/**
	 * Clears the file-based autoloader cache.
	 *
	 * @return void
	 */
	private static function clear_file_cache(): void {
		foreach (self::get_file_cache_paths() as $cache_file) {
			if (is_string($cache_file) && $cache_file !== '' && is_file($cache_file)) {
				@unlink($cache_file);
			}
		}
		self::notice("Autoloader file cache cleared.");
	}

	/**
	 * Returns all configured cache file paths.
	 *
	 * @return array<string,string>
	 */
	private static function get_file_cache_paths(): array {
		if (self::$classes_file !== null) {
			return [
				'classes'     => self::$classes_file,
				'interfaces'  => self::$interfaces_file,
				'inheritance' => self::$inheritance_file,
				'attributes'  => self::$attributes_file,
			];
		}

		$cache_dir              = self::get_file_cache_directory();
		self::$classes_file     = $cache_dir . '/' . self::CLASSES_FILE;
		self::$interfaces_file  = $cache_dir . '/' . self::INTERFACES_FILE;
		self::$inheritance_file = $cache_dir . '/' . self::INHERITANCE_FILE;
		self::$attributes_file  = $cache_dir . '/' . self::ATTRIBUTES_FILE;

		return [
			'classes'     => self::$classes_file,
			'interfaces'  => self::$interfaces_file,
			'inheritance' => self::$inheritance_file,
			'attributes'  => self::$attributes_file,
		];
	}

	/**
	 * Returns one cache file path by filename constant.
	 *
	 * @param string $file_name
	 *
	 * @return string
	 */
	private static function get_file_cache_path(string $file_name): string {
		$cache_files = self::get_file_cache_paths();
		if ($file_name === self::CLASSES_FILE) {
			return $cache_files['classes'] ?? '';
		}
		if ($file_name === self::INTERFACES_FILE) {
			return $cache_files['interfaces'] ?? '';
		}
		if ($file_name === self::INHERITANCE_FILE) {
			return $cache_files['inheritance'] ?? '';
		}
		if ($file_name === self::ATTRIBUTES_FILE) {
			return $cache_files['attributes'] ?? '';
		}

		return '';
	}

	/**
	 * Resolves the file cache directory.
	 *
	 * @return string
	 */
	private static function get_file_cache_directory(): string {
		$configured_dir = trim((string) ($_ENV['auto_loader_cache_path'] ?? ''));
		if ($configured_dir !== '') {
			return rtrim($configured_dir, '/');
		}

		return rtrim(sys_get_temp_dir(), '/') . '/fusionpbx_cache';
	}

	/**
	 * Returns attribute metadata grouped by target type and target name.
	 *
	 * @param string $target_type Optional target type (class|interface|trait|function|method|property|constant|class_constant)
	 * @param string $target_name Optional target name within the selected target type
	 *
	 * @return array
	 */
	public function get_attributes(string $target_type = '', string $target_name = ''): array {
		if (empty($this->attributes) || !is_array($this->attributes)) {
			return $this->default_attribute_map();
		}

		if ($target_type === '') {
			return $this->attributes;
		}

		if (!isset($this->attributes[$target_type])) {
			return [];
		}

		if ($target_name === '') {
			return $this->attributes[$target_type];
		}

		return $this->attributes[$target_type][$target_name] ?? [];
	}

	/**
	 * Returns all discovered attribute entries matching the given attribute name.
	 * Name matching is case-insensitive and supports either fully-qualified or short names.
	 *
	 * @param string $attribute_name The attribute class name to match
	 *
	 * @return array
	 */
	public function get_attributes_by_name(string $attribute_name): array {
		$attribute_name = trim($attribute_name, " \n\r\t\v\x00\\");
		if ($attribute_name === '') {
			return [];
		}

		$lookup_full  = strtolower($attribute_name);
		$lookup_short = strtolower($this->get_short_name($attribute_name));
		$result       = [];

		foreach ($this->attributes as $targets) {
			foreach ($targets as $entries) {
				foreach ($entries as $entry) {
					$entry_name  = strtolower($entry['attribute'] ?? '');
					$entry_short = strtolower($this->get_short_name($entry['attribute'] ?? ''));
					if ($entry_name === $lookup_full || $entry_short === $lookup_short) {
						$result[] = $entry;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Returns all attributes declared on the class/interface/trait.
	 *
	 * @param string $class_name
	 *
	 * @return array
	 */
	public function get_class_attributes(string $class_name): array {
		$class_name = trim($class_name);

		return $this->attributes['class'][$class_name] ?? $this->attributes['interface'][$class_name] ?? $this->attributes['trait'][$class_name] ?? [];
	}

	/**
	 * Returns all attributes declared on a function.
	 *
	 * @param string $function_name
	 *
	 * @return array
	 */
	public function get_function_attributes(string $function_name): array {
		return $this->attributes['function'][$function_name] ?? [];
	}

	/**
	 * Returns all attributes declared on a method.
	 *
	 * @param string $class_name
	 * @param string $method_name
	 *
	 * @return array
	 */
	public function get_method_attributes(string $class_name, string $method_name): array {
		$key = trim($class_name) . '::' . trim($method_name);

		return $this->attributes['method'][$key] ?? [];
	}

	/**
	 * Returns a callable-ready list of methods, including class names.
	 *
	 * @param string $attribute_name Optional attribute class name filter
	 *
	 * @return array
	 */
	public function get_method_list(string $attribute_name = ''): array {
		$attribute_name = trim($attribute_name, " \n\r\t\v\x00\\");
		$lookup_full    = strtolower($attribute_name);
		$lookup_short   = strtolower($this->get_short_name($attribute_name));
		$result         = [];

		foreach ($this->attributes['method'] ?? [] as $target_name => $entries) {
			foreach ($entries as $entry) {
				$entry_name  = strtolower($entry['attribute'] ?? '');
				$entry_short = strtolower($this->get_short_name($entry['attribute'] ?? ''));

				if ($attribute_name !== '' && $entry_name !== $lookup_full && $entry_short !== $lookup_short) {
					continue;
				}

				$class_name  = $entry['class'] ?? '';
				$method_name = $entry['method'] ?? '';

				// support older cache entries by parsing the target when needed
				if (($class_name === '' || $method_name === '') && strpos($target_name, '::') !== false) {
					$parts       = explode('::', $target_name, 2);
					$class_name  = $parts[0] ?? '';
					$method_name = $parts[1] ?? '';
				}

				if ($class_name === '' || $method_name === '') {
					continue;
				}

				$result[] = [
					'class'     => $class_name,
					'method'    => $method_name,
					'callable'  => [$class_name, $method_name],
					'attribute' => $entry['attribute'] ?? '',
					'arguments' => $entry['arguments'] ?? '',
					'raw'       => $entry['raw'] ?? '',
					'target'    => $target_name,
					'file'      => $entry['file'] ?? '',
					'line'      => $entry['line'] ?? 0,
				];
			}
		}

		return $result;
	}

	/**
	 * Returns all attributes declared on a constant.
	 *
	 * @param string $constant_name Constant name
	 * @param string $class_name Optional class name for class constants
	 *
	 * @return array
	 */
	public function get_constant_attributes(string $constant_name, string $class_name = ''): array {
		$constant_name = trim($constant_name);
		if ($class_name !== '') {
			$key = trim($class_name) . '::' . $constant_name;

			return $this->attributes['class_constant'][$key] ?? [];
		}

		return $this->attributes['constant'][$constant_name] ?? [];
	}

	/**
	 * Returns all attributes declared on a class property.
	 *
	 * @param string $class_name
	 * @param string $property_name
	 *
	 * @return array
	 */
	public function get_property_attributes(string $class_name, string $property_name): array {
		$property_name = ltrim(trim($property_name), '$');
		$key           = trim($class_name) . '::$' . $property_name;

		return $this->attributes['property'][$key] ?? [];
	}

	/**
	 * Returns the default attribute map shape.
	 *
	 * @return array
	 */
	private function default_attribute_map(): array {
		return [
			'class'          => [],
			'interface'      => [],
			'trait'          => [],
			'function'       => [],
			'method'         => [],
			'property'       => [],
			'constant'       => [],
			'class_constant' => [],
		];
	}

	/**
	 * Parses a single attribute file and records declaration targets.
	 *
	 * @param string $file
	 *
	 * @return void
	 */
	private function parse_attribute_file(string $file): void {
		$file_content = @file_get_contents($file);
		if ($file_content === false || $file_content === '') {
			return;
		}

		$tokens             = token_get_all($file_content);
		$namespace          = '';
		$brace_level        = 0;
		$current_class      = '';
		$next_class         = '';
		$class_brace_level  = null;
		$expect_class_brace = false;
		$pending_attributes = [];

		$token_count = count($tokens);
		for ($i = 0; $i < $token_count; $i++) {
			$token = $tokens[$i];

			if (is_string($token)) {
				if ($token === '{') {
					$brace_level++;
					if ($expect_class_brace) {
						$current_class      = $next_class;
						$class_brace_level  = $brace_level;
						$expect_class_brace = false;
					}
				} else if ($token === '}') {
					if ($class_brace_level !== null && $brace_level === $class_brace_level) {
						$current_class     = '';
						$class_brace_level = null;
					}
					$brace_level = max(0, $brace_level - 1);
				}
				continue;
			}

			$token_id   = $token[0];
			$token_text = $token[1];
			$token_line = $token[2] ?? 0;

			if ($token_id === T_NAMESPACE) {
				$namespace_parts = [];
				for ($j = $i + 1; $j < $token_count; $j++) {
					$namespace_token = $tokens[$j];
					if (is_string($namespace_token) && ($namespace_token === ';' || $namespace_token === '{')) {
						$i = $j;
						break;
					}
					if (is_array($namespace_token) && $this->is_namespace_token($namespace_token[0])) {
						$namespace_parts[] = $namespace_token[1];
					}
				}
				$namespace = implode('', $namespace_parts);
				continue;
			}

			if ($token_id === T_ATTRIBUTE) {
				$attribute_group = $this->consume_attribute_group($tokens, $i);
				if (!empty($attribute_group['attributes'])) {
					$pending_attributes = array_merge($pending_attributes, $attribute_group['attributes']);
				}
				continue;
			}

			if ($token_id === T_CLASS || $token_id === T_INTERFACE || $token_id === T_TRAIT) {
				$prev_index       = $this->next_significant_index($tokens, $i - 1, -1);
				$previous_was_new = ($prev_index !== null && is_array($tokens[$prev_index]) && $tokens[$prev_index][0] === T_NEW);
				if ($previous_was_new) {
					$pending_attributes = [];
					continue;
				}

				$name_index = $this->next_significant_index($tokens, $i + 1, 1);
				if ($name_index !== null && is_array($tokens[$name_index]) && $tokens[$name_index][0] === T_STRING) {
					$short_name         = trim($tokens[$name_index][1]);
					$full_name          = $namespace !== '' ? $namespace . '\\' . $short_name : $short_name;
					$next_class         = $full_name;
					$expect_class_brace = true;

					$target_type = strtolower($token_text);
					$this->add_attributes_to_target($pending_attributes, $target_type, $full_name, $file, $token_line);
				}

				$pending_attributes = [];
				continue;
			}

			if ($token_id === T_FUNCTION) {
				$name_index = $this->next_significant_index($tokens, $i + 1, 1);
				if ($name_index !== null && $tokens[$name_index] === '&') {
					$name_index = $this->next_significant_index($tokens, $name_index + 1, 1);
				}

				if ($name_index !== null && is_array($tokens[$name_index]) && $tokens[$name_index][0] === T_STRING) {
					$name      = trim($tokens[$name_index][1]);
					$modifiers = $this->extract_method_modifiers($tokens, $i);
					if ($current_class !== '') {
						$target_name = $current_class . '::' . $name;
						$this->add_attributes_to_target($pending_attributes, 'method', $target_name, $file, $token_line, $modifiers);
					} else {
						$target_name = $namespace !== '' ? $namespace . '\\' . $name : $name;
						$this->add_attributes_to_target($pending_attributes, 'function', $target_name, $file, $token_line, $modifiers);
					}
				}

				$pending_attributes = [];
				continue;
			}

			if ($token_id === T_CONST) {
				$constant_names       = [];
				$expect_constant_name = true;
				for ($j = $i + 1; $j < $token_count; $j++) {
					$current_token = $tokens[$j];
					if ($current_token === ';') {
						$i = $j;
						break;
					}

					if ($current_token === ',') {
						$expect_constant_name = true;
						continue;
					}

					if ($expect_constant_name && is_array($current_token) && $current_token[0] === T_STRING) {
						$constant_names[]     = $current_token[1];
						$expect_constant_name = false;
					}
				}

				foreach ($constant_names as $constant_name) {
					if ($current_class !== '') {
						$target_name = $current_class . '::' . $constant_name;
						$this->add_attributes_to_target($pending_attributes, 'class_constant', $target_name, $file, $token_line);
					} else {
						$target_name = $namespace !== '' ? $namespace . '\\' . $constant_name : $constant_name;
						$this->add_attributes_to_target($pending_attributes, 'constant', $target_name, $file, $token_line);
					}
				}

				$pending_attributes = [];
				continue;
			}

			if ($token_id === T_VARIABLE && !empty($pending_attributes) && $current_class !== '') {
				$property_name = ltrim(trim($token_text), '$');
				$target_name   = $current_class . '::$' . $property_name;
				$this->add_attributes_to_target($pending_attributes, 'property', $target_name, $file, $token_line);
				$pending_attributes = [];
				continue;
			}
		}
	}

	/**
	 * Stores attributes under the given target.
	 *
	 * @param array  $attributes
	 * @param string $target_type
	 * @param string $target_name
	 * @param string $file
	 * @param int    $line
	 *
	 * @return void
	 */
	private function add_attributes_to_target(array $attributes, string $target_type, string $target_name, string $file, int $line, array $modifiers = []): void {
		if (empty($attributes) || !isset($this->attributes[$target_type])) {
			return;
		}

		if (empty($this->attributes[$target_type][$target_name])) {
			$this->attributes[$target_type][$target_name] = [];
		}

		foreach ($attributes as $attribute) {
			$entry = [
				'attribute'   => $attribute['name'] ?? '',
				'arguments'   => $attribute['arguments'] ?? '',
				'raw'         => $attribute['raw'] ?? '',
				'target_type' => $target_type,
				'target'      => $target_name,
				'file'        => $file,
				'line'        => $line,
			];

			if ($target_type === 'method' && strpos($target_name, '::') !== false) {
				$parts               = explode('::', $target_name, 2);
				$entry['class']      = $parts[0] ?? '';
				$entry['method']     = $parts[1] ?? '';
				$entry['is_static']  = in_array('static', $modifiers, true);
				$entry['visibility'] = $this->extract_visibility($modifiers);
			}

			$this->attributes[$target_type][$target_name][] = $entry;
		}
	}

	/**
	 * Reads a complete attribute group and returns normalized attribute entries.
	 *
	 * @param array $tokens
	 * @param int   $index Current token index, updated to the end of the group
	 *
	 * @return array
	 */
	private function consume_attribute_group(array $tokens, int &$index): array {
		$buffer     = '';
		$depth      = 0;
		$start_line = is_array($tokens[$index]) ? ($tokens[$index][2] ?? 0) : 0;

		for ($j = $index; $j < count($tokens); $j++) {
			$current = $tokens[$j];
			$text    = is_array($current) ? $current[1] : $current;
			$buffer .= $text;

			$depth += substr_count($text, '[');
			$depth -= substr_count($text, ']');

			if ($depth <= 0 && strpos($text, ']') !== false) {
				$index = $j;
				break;
			}
		}

		$content = trim($buffer);
		$inside  = trim($content, "#[] \t\n\r\0\x0B");
		$parts   = $this->split_top_level_csv($inside);

		$result = [];
		foreach ($parts as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}

			$name      = $part;
			$arguments = '';
			if (preg_match('/^([\\\\a-zA-Z_][\\\\a-zA-Z0-9_]*)(?:\s*\((.*)\))?$/s', $part, $match)) {
				$name      = trim($match[1]);
				$arguments = isset($match[2]) ? trim($match[2]) : '';
			}

			$result[] = [
				'name'      => $name,
				'arguments' => $arguments,
				'raw'       => $part,
				'line'      => $start_line,
			];
		}

		return ['attributes' => $result];
	}

	/**
	 * Splits a comma-delimited list while respecting nested parentheses and brackets.
	 *
	 * @param string $value
	 *
	 * @return array
	 */
	private function split_top_level_csv(string $value): array {
		$result        = [];
		$current       = '';
		$paren_depth   = 0;
		$bracket_depth = 0;
		$length        = strlen($value);

		for ($i = 0; $i < $length; $i++) {
			$char = $value[$i];
			if ($char === '(') {
				$paren_depth++;
			} else if ($char === ')') {
				$paren_depth = max(0, $paren_depth - 1);
			} else if ($char === '[') {
				$bracket_depth++;
			} else if ($char === ']') {
				$bracket_depth = max(0, $bracket_depth - 1);
			}

			if ($char === ',' && $paren_depth === 0 && $bracket_depth === 0) {
				$result[] = trim($current);
				$current  = '';
				continue;
			}

			$current .= $char;
		}

		if (trim($current) !== '') {
			$result[] = trim($current);
		}

		return $result;
	}

	/**
	 * Returns the next significant token index.
	 *
	 * @param array $tokens
	 * @param int   $start_index
	 * @param int   $direction 1 forward, -1 backward
	 *
	 * @return int|null
	 */
	private function next_significant_index(array $tokens, int $start_index, int $direction = 1): ?int {
		for ($i = $start_index; $i >= 0 && $i < count($tokens); $i += $direction) {
			$token = $tokens[$i];
			if (is_string($token)) {
				if (trim($token) !== '') {
					return $i;
				}
				continue;
			}

			if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Extracts method modifiers (static, public, private, protected, abstract, final) from tokens before a function declaration.
	 *
	 * @param array $tokens
	 * @param int   $function_index Index of the T_FUNCTION token
	 *
	 * @return array<int,string>
	 */
	private function extract_method_modifiers(array $tokens, int $function_index): array {
		$modifiers = [];

		// Look backwards from T_FUNCTION for modifiers
		for ($i = $function_index - 1; $i >= 0; --$i) {
			$token = $tokens[$i];

			if (is_string($token)) {
				// Stop at structural boundaries (opening brace, semicolon, closing paren)
				if ($token === '{' || $token === '}' || $token === ';') {
					break;
				}
				// Stop at #[ (attribute start)
				if ($token === '[' && $i > 0 && $tokens[$i - 1] === '#') {
					break;
				}
				continue;
			}

			if (!is_array($token)) {
				continue;
			}

			$token_id   = $token[0];
			$token_text = trim($token[1] ?? '');

			// Stop at attribute start
			if ($token_id === T_ATTRIBUTE) {
				break;
			}

			// Capture modifier keywords
			if ($token_id === T_STATIC ||
					$token_id === T_PUBLIC ||
					$token_id === T_PROTECTED ||
					$token_id === T_PRIVATE ||
					$token_id === T_ABSTRACT ||
					$token_id === T_FINAL) {
				$modifiers[] = strtolower($token_text);
			}
		}

		return array_reverse($modifiers);
	}

	/**
	 * Extracts visibility (public, protected, private) from modifier list.
	 *
	 * @param array<int,string> $modifiers
	 *
	 * @return string
	 */
	private function extract_visibility(array $modifiers): string {
		if (in_array('private', $modifiers, true)) {
			return 'private';
		}
		if (in_array('protected', $modifiers, true)) {
			return 'protected';
		}

		// public is the default if no visibility modifier is specified
		return 'public';
	}

	/**
	 * Is namespace token.
	 * @param mixed $token_id
	 * @return bool
	 */
	private function is_namespace_token(int $token_id): bool {
		$namespace_tokens = [T_STRING, T_NS_SEPARATOR];

		if (defined('T_NAME_QUALIFIED')) {
			$namespace_tokens[] = T_NAME_QUALIFIED;
		}

		if (defined('T_NAME_FULLY_QUALIFIED')) {
			$namespace_tokens[] = T_NAME_FULLY_QUALIFIED;
		}

		if (defined('T_NAME_RELATIVE')) {
			$namespace_tokens[] = T_NAME_RELATIVE;
		}

		return in_array($token_id, $namespace_tokens, true);
	}

	/**
	 * Returns the short (basename) form of a class-like name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private function get_short_name(string $name): string {
		$parts = explode('\\', trim($name, '\\'));

		return end($parts) ?: '';
	}

	/**
	 * Returns a list of classes loaded by the auto_loader. If no classes have been loaded an empty array is returned.
	 *
	 * @param string $parent Optional parent class name to filter the list of classes that has the given parent class.
	 *
	 * @return array List of classes loaded by the auto_loader or empty array
	 */
	public function get_class_list(string $parent = ''): array {
		$classes = [];
		// make sure we can return values if no classes have been loaded
		if (!empty($this->classes)) {
			if ($parent !== '') {
				foreach ($this->classes as $class_name => $path) {
					if (is_subclass_of($class_name, $parent)) {
						$classes[$class_name] = $path;
					}
				}
			} else {
				$classes = $this->classes;
			}
		}

		return $classes;
	}

	/**
	 * Returns a list of classes implementing the interface or any interface that extends it
	 *
	 * @param string $interface_name
	 *
	 * @return array
	 */
	public function get_interface_list(string $interface_name): array {
		// make sure we can return values
		if (empty($this->classes) || empty($this->interfaces)) {
			return [];
		}

		// get direct implementers of this interface
		$result = $this->interfaces[$interface_name] ?? [];

		// find all child interfaces (interfaces that extend this interface)
		$child_interfaces = $this->get_child_interfaces($interface_name);

		// for each child interface, get its implementers
		foreach ($child_interfaces as $child_interface) {
			if (!empty($this->interfaces[$child_interface])) {
				$result = array_merge($result, $this->interfaces[$child_interface]);
			}
		}

		// remove duplicates and return
		return array_unique($result);
	}

	/**
	 * Returns only classes that directly implement the given interface,
	 * without including classes that implement child/extended interfaces.
	 * This is used for global hook dispatch where only direct implementers
	 * of a generic interface (e.g., page_edit_hook) should be invoked,
	 * while app-specific hooks use get_interface_list() for full resolution.
	 *
	 * @param string $interface_name The interface to find direct implementers for
	 *
	 * @return array List of class names that directly implement the interface
	 */
	public function get_direct_implementers(string $interface_name): array {
		if (empty($this->classes) || empty($this->interfaces)) {
			return [];
		}

		return $this->interfaces[$interface_name] ?? [];
	}

	/**
	 * Recursively finds all interfaces that extend the given interface
	 *
	 * @param string $interface_name The interface to find children for
	 * @param array $visited Track visited interfaces to avoid infinite loops
	 *
	 * @return array List of child interface names
	 */
	private function get_child_interfaces(string $interface_name, array &$visited = []): array {
		$children = [];

		// Mark as visited to prevent infinite recursion
		if (in_array($interface_name, $visited, true)) {
			return [];
		}
		$visited[] = $interface_name;

		// Find all interfaces that extend this interface
		foreach ($this->inheritance as $class_name => $parent_name) {
			if ($parent_name === $interface_name) {
				// Record this as a child
				$children[] = $class_name;

				// Recursively find children of this child
				$children = array_merge($children, $this->get_child_interfaces($class_name, $visited));
			}
		}

		return $children;
	}

	/**
	 * Returns a list of all user defined interfaces that have been registered.
	 *
	 * @return array
	 */
	public function get_interfaces(): array {
		if (!empty($this->interfaces)) {
			return $this->interfaces;
		}

		return [];
	}

	/**
	 * Returns a list of all discovered traits keyed by trait name.
	 *
	 * @return array
	 */
	public function get_traits(): array {
		if (!empty($this->traits)) {
			return $this->traits;
		}

		return [];
	}

	/**
	 * Load a class, interface, or trait when requested by the PHP engine.
	 *
	 * @param string $class_name The class name that needs to be loaded
	 *
	 * @return bool True if the class is loaded or false when the class is not found
	 */
	public function loader($class_name): bool {
		$this->debug("Attempting to load class '$class_name'");

		// sanitize the class name (preserve backslashes for namespaces)
		$class_name  = preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $class_name);
		$lookup_name = $class_name;
		if (!isset($this->classes[$lookup_name])) {
			$lower_lookup_name = strtolower($lookup_name);
			if (isset($this->classes[$lower_lookup_name])) {
				$lookup_name = $lower_lookup_name;
			}
		}

		// find the path using the class_name as the key in the classes array
		if (isset($this->classes[$lookup_name])) {
			// include the class or interface
			$result = @include_once $this->classes[$lookup_name];

			// check for edge case where the file was deleted after cache creation
			if ($result === false) {
				// send to syslog when debugging
				$this->error("class '$class_name' registered but include failed (file deleted?). Removed from cache.");

				// remove the class from the array
				unset($this->classes[$lookup_name]);

				// return failure
				return false;
			}

			// return success
			return true;
		}

		// cache miss
		if (empty($this->missing_class_warnings[$class_name])) {
			$this->warning("class '$class_name' not found in cache");
			$this->missing_class_warnings[$class_name] = true;
		}

		return false;
	}

	public static function unload_me(auto_loader $loader): void {
		spl_autoload_unregister([$loader, 'loader']);
		unset($loader);
	}
}
