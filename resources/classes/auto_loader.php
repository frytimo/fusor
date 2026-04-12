<?php

/*
  FusionPBX
  Version: MPL 1.1

  The contents of this file are subject to the Mozilla Public License Version
  1.1 (the "License"); you may not use this file except in compliance with
  the License. You may obtain a copy of the License at
  http://www.mozilla.org/MPL/

  Software distributed under the License is distributed on an "AS IS" basis,
  WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
  for the specific language governing rights and limitations under the
  License.

  The Original Code is FusionPBX

  The Initial Developer of the Original Code is
  Mark J Crane <markjcrane@fusionpbx.com>
  Portions created by the Initial Developer are Copyright (C) 2008-2024
  the Initial Developer. All Rights Reserved.

  Contributor(s):
  Mark J Crane <markjcrane@fusionpbx.com>
  Tim Fry <tim@fusionpbx.com>
 */

/**
 * Auto Loader class
 * Searches for project files when a class is required. Debugging mode can be set using:
 * - export DEBUG=1
 *      OR
 * - debug=true is appended to the url
 */
class auto_loader {

	const CLASSES_KEY = 'autoloader_classes';
	const CLASSES_FILE = 'autoloader_cache.php';
	const INTERFACES_KEY = "autoloader_interfaces";
	const INTERFACES_FILE = "autoloader_interface_cache.php";
	const INHERITANCE_KEY = "autoloader_inheritance";
	const INHERITANCE_FILE = "autoloader_inheritance_cache.php";
	const ATTRIBUTES_KEY = 'autoloader_attributes';
	const ATTRIBUTES_FILE = 'autoloader_attributes_cache.php';
	const CACHE_VERSION_KEY = 'autoloader_cache_version';
	const CACHE_VERSION = 7;
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
	private bool $cache_enabled = true;

	/**
	 * Initializes the class and sets up caching mechanisms.
	 *
	 * @param bool $disable_cache If true, disables cache usage. Defaults to false.
	 */
	public function __construct($disable_cache = false) {

		//set if we can use RAM cache
		$this->apcu_enabled = function_exists('apcu_enabled') && apcu_enabled();
		$this->cache_enabled = !$disable_cache && $this->cache_enabled_from_env();

		//set classes cache location
		if (empty(self::$classes_file)) {
			self::$classes_file = self::cache_file_path(self::CLASSES_FILE);
		}

		//set interface cache location
		if (empty(self::$interfaces_file)) {
			self::$interfaces_file = self::cache_file_path(self::INTERFACES_FILE);
		}

		//set inheritance cache location
		if (empty(self::$inheritance_file)) {
			self::$inheritance_file = self::cache_file_path(self::INHERITANCE_FILE);
		}

		//set attribute cache location
		if (empty(self::$attributes_file)) {
			self::$attributes_file = self::cache_file_path(self::ATTRIBUTES_FILE);
		}

		//classes must be loaded before this object is registered
		if (!$this->cache_enabled || !$this->load_cache()) {
			//cache miss so load them
			$this->reload_classes();
			if ($this->cache_enabled) {
				//update the cache after loading classes array
				$this->update_cache();
			}
		}
		//register this object to load any unknown classes
		spl_autoload_register([$this, 'loader']);
	}

	/**
	 * Loads the class cache from various sources.
	 *
	 * @return bool True if the cache is loaded successfully, false otherwise.
	 */
	public function load_cache(): bool {
		$this->classes = [];
		$this->interfaces = [];
		$this->inheritance = [];
		$this->traits = [];
		$this->attributes = $this->default_attribute_map();

		//check APCu cache version
		$apcu_version_valid = false;
		if ($this->apcu_enabled) {
			$cached_version = apcu_fetch(self::CACHE_VERSION_KEY, $version_exists);
			if ($version_exists && $cached_version === self::CACHE_VERSION) {
				$apcu_version_valid = true;
			} else if ($version_exists) {
				//clear stale APCu cache
				apcu_delete(self::CACHE_VERSION_KEY);
				apcu_delete(self::CLASSES_KEY);
				apcu_delete(self::INTERFACES_KEY);
				apcu_delete(self::INHERITANCE_KEY);
				apcu_delete(self::ATTRIBUTES_KEY);
			}
		}

		//use apcu when available and version is valid
		if ($this->apcu_enabled && $apcu_version_valid && apcu_exists(self::CLASSES_KEY)) {
			$this->classes = apcu_fetch(self::CLASSES_KEY, $classes_cached);
			$this->interfaces = apcu_fetch(self::INTERFACES_KEY, $interfaces_cached);
			$this->inheritance = apcu_fetch(self::INHERITANCE_KEY, $inheritance_cached);
			$this->attributes = apcu_fetch(self::ATTRIBUTES_KEY, $attributes_cached);
			if (!$attributes_cached || !is_array($this->attributes)) {
				$this->attributes = $this->default_attribute_map();
			}
			$this->rebuild_traits_from_classes();
			//verify we got valid data
			if ($classes_cached && $interfaces_cached && $inheritance_cached && !empty($this->classes)) {
				return true;
			}
		}

		//check file cache version and load if valid
		$file_cache_valid = false;
		if (file_exists(self::$classes_file)) {
			$cached_data = include self::$classes_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$this->classes = $cached_data['classes'] ?? [];
				$file_cache_valid = true;
			} else {
				//delete stale file cache
				@unlink(self::$classes_file);
			}
		}

		//do the same for interface to class mappings
		if ($file_cache_valid && file_exists(self::$interfaces_file)) {
			$cached_data = include self::$interfaces_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$this->interfaces = $cached_data['interfaces'] ?? [];
			} else {
				//delete stale file cache
				@unlink(self::$interfaces_file);
				$file_cache_valid = false;
			}
		}

		//do the same for inheritance mappings
		if ($file_cache_valid && file_exists(self::$inheritance_file)) {
			$cached_data = include self::$inheritance_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$this->inheritance = $cached_data['inheritance'] ?? [];
			} else {
				//delete stale file cache
				@unlink(self::$inheritance_file);
				$file_cache_valid = false;
			}
		}

		//do the same for attributes mappings
		if ($file_cache_valid && file_exists(self::$attributes_file)) {
			$cached_data = include self::$attributes_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$cached_attributes = $cached_data['attributes'] ?? [];
				$this->attributes = is_array($cached_attributes) ? $cached_attributes : $this->default_attribute_map();
			} else {
				//delete stale file cache
				@unlink(self::$attributes_file);
				$file_cache_valid = false;
			}
		}

		//populate apcu cache from file cache if available and valid
		if ($this->apcu_enabled && $file_cache_valid && !empty($this->classes)) {
			apcu_store(self::CACHE_VERSION_KEY, self::CACHE_VERSION);
			apcu_store(self::CLASSES_KEY, $this->classes);
			apcu_store(self::INTERFACES_KEY, $this->interfaces);
			apcu_store(self::INHERITANCE_KEY, $this->inheritance);
			apcu_store(self::ATTRIBUTES_KEY, $this->attributes);
		}

		$this->rebuild_traits_from_classes();

		//return true when we have classes and false if the array is still empty
		return ($file_cache_valid && !empty($this->classes) && !empty($this->interfaces));
	}

	/**
	 * Reloads classes and interfaces from the project's resources.
	 *
	 * This method scans all PHP files in the specified locations, parses their contents,
	 * and updates the internal storage of classes and interfaces. It also processes
	 * implementation relationships between classes and interfaces.
	 *
	 * @return void
	 */
	public function reload_classes() {
		//set project path using magic dir constant
		$project_path = $this->project_path();

		//build the array of all locations for classes in specific order
		$search_path = $this->class_search_paths($project_path);

		//get all php files for each path
		$files = [];
		foreach ($search_path as $path) {
			$files = array_merge($files, glob($path));
		}

		//reset the current array
		$class_list = [];
		$this->traits = [];
		$this->attributes = $this->default_attribute_map();

		//store the class name (key) and the path (value)
		foreach ($files as $file) {
			//index attributes declared on classes, methods, properties, and constants
			$this->parse_attribute_file($file);

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
					$full_name = $namespace . $name;

					// Store the class/interface/trait with its file overwriting any existing declaration.
					$this->classes[$full_name] = $file;
					if ($type === 'trait') {
						$this->traits[$full_name] = $file;
					}

					// Track inheritance (what this class/interface extends)
					if (isset($match[3]) && trim($match[3]) !== '') {
						$parent_name = trim($match[3], " \n\r\t\v\x00\\");
						$this->inheritance[$full_name] = $parent_name;
					}

					// If it's a class that implements interfaces, process the implements clause.
					if ($type === 'class' && isset($match[4]) && trim($match[4]) !== '') {
						// Split the interface list by commas.
						$interface_list = explode(',', $match[4]);
						foreach ($interface_list as $interface) {
							$interface_name = trim($interface, " \n\r\t\v\x00\\");
							// Check that it is declared as an array so we can record the classes
							if (empty($this->interfaces[$interface_name])) {
								$this->interfaces[$interface_name] = [];
							}

							// Ensure we don't already have the class recorded
							if (!in_array($full_name, $this->interfaces[$interface_name], true)) {
								// Record the classes that implement interface sorting by namspace and class name
								$this->interfaces[$interface_name][] = $full_name;
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

		//scan explicit attribute metadata files (IonCube compatible)
		$this->reload_attributes($project_path);
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
	 * Updates the cache by writing the classes and interfaces to files on disk.
	 *
	 * @return bool True if the update was successful, false otherwise
	 */
	public function update_cache(): bool {
		//guard against writing an empty file
		if (empty($this->classes)) {
			return false;
		}

		//update RAM cache when available
		if ($this->apcu_enabled) {
			apcu_store(self::CACHE_VERSION_KEY, self::CACHE_VERSION);
			apcu_store(self::CLASSES_KEY, $this->classes);
			apcu_store(self::INTERFACES_KEY, $this->interfaces);
			apcu_store(self::INHERITANCE_KEY, $this->inheritance);
			apcu_store(self::ATTRIBUTES_KEY, $this->attributes);
		}

		//prepare versioned data structure for classes
		$classes_data = [
			'version' => self::CACHE_VERSION,
			'classes' => $this->classes,
		];
		$classes_array = var_export($classes_data, true);

		//put the array in a form that it can be loaded directly to an array
		$class_result = file_put_contents(self::$classes_file, "<?php\n return " . $classes_array . ";\n");
		if ($class_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		//prepare versioned data structure for interfaces
		$interfaces_data = [
			'version' => self::CACHE_VERSION,
			'interfaces' => $this->interfaces,
		];
		$interfaces_array = var_export($interfaces_data, true);

		//put the array in a form that it can be loaded directly to an array
		$interface_result = file_put_contents(self::$interfaces_file, "<?php\n return " . $interfaces_array . ";\n");
		if ($interface_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		//prepare versioned data structure for inheritance
		$inheritance_data = [
			'version' => self::CACHE_VERSION,
			'inheritance' => $this->inheritance,
		];
		$inheritance_array = var_export($inheritance_data, true);

		//put the array in a form that it can be loaded directly to an array
		$inheritance_result = file_put_contents(self::$inheritance_file, "<?php\n return " . $inheritance_array . ";\n");
		if ($inheritance_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		//prepare versioned data structure for attributes
		$attributes_data = [
			'version' => self::CACHE_VERSION,
			'attributes' => $this->attributes,
		];
		$attributes_array = var_export($attributes_data, true);

		//put the array in a form that it can be loaded directly to an array
		$attribute_result = file_put_contents(self::$attributes_file, "<?php\n return " . $attributes_array . ";\n");
		if ($attribute_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		$result = ($class_result && $interface_result && $inheritance_result && $attribute_result);

		return $result;
	}

	/**
	 * Logs a message at the specified level
	 *
	 * @param int    $level   The log level (e.g. E_ERROR)
	 * @param string $message The log message
	 */
	private static function log(int $level, string $message): void {
		if (filter_var($_REQUEST['debug'] ?? false, FILTER_VALIDATE_BOOLEAN) || filter_var(getenv('DEBUG') ?? false, FILTER_VALIDATE_BOOLEAN)) {
			openlog("PHP", LOG_PID | LOG_PERROR, LOG_LOCAL0);
			syslog($level, "[auto_loader] " . $message);
			closelog();
		}
	}

	/**
	 * Main method used to update internal state by clearing cache, reloading classes and updating cache.
	 *
	 * @return void
	 * @see \auto_loader::clear_cache()
	 * @see \auto_loader::reload_classes()
	 * @see \auto_loader::update_cache()
	 */
	public function update() {
		self::clear_cache();
		$this->reload_classes();
		if ($this->cache_enabled) {
			$this->update_cache();
		}
	}

	/**
	 * Clears the cache of stored classes and interfaces.
	 *
	 * @return void
	 */
	public static function clear_cache() {

		//check for apcu cache
		if (function_exists('apcu_enabled') && apcu_enabled()) {
			apcu_delete(self::CACHE_VERSION_KEY);
			apcu_delete(self::CLASSES_KEY);
			apcu_delete(self::INTERFACES_KEY);
			apcu_delete(self::INHERITANCE_KEY);
			apcu_delete(self::ATTRIBUTES_KEY);
		}

		//set default file
		if (empty(self::$classes_file)) {
			self::$classes_file = self::cache_file_path(self::CLASSES_FILE);
		}

		//set file to clear
		$classes_file = self::$classes_file;

		//remove the file when it exists
		if (file_exists($classes_file)) {
			@unlink($classes_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		if (empty(self::$interfaces_file)) {
			self::$interfaces_file = self::cache_file_path(self::INTERFACES_FILE);
		}

		//set interfaces file to clear
		$interfaces_file = self::$interfaces_file;

		//remove the file when it exists
		if (file_exists($interfaces_file)) {
			@unlink($interfaces_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		if (empty(self::$inheritance_file)) {
			self::$inheritance_file = self::cache_file_path(self::INHERITANCE_FILE);
		}

		//set inheritance file to clear
		$inheritance_file = self::$inheritance_file;

		//remove the file when it exists
		if (file_exists($inheritance_file)) {
			@unlink($inheritance_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		if (empty(self::$attributes_file)) {
			self::$attributes_file = self::cache_file_path(self::ATTRIBUTES_FILE);
		}

		//set attribute file to clear
		$attributes_file = self::$attributes_file;

		//remove the file when it exists
		if (file_exists($attributes_file)) {
			@unlink($attributes_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}
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

		$lookup_full = strtolower($attribute_name);
		$lookup_short = strtolower($this->get_short_name($attribute_name));
		$result = [];

		foreach ($this->attributes as $targets) {
			foreach ($targets as $entries) {
				foreach ($entries as $entry) {
					$entry_name = strtolower($entry['attribute'] ?? '');
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
		$lookup_full = strtolower($attribute_name);
		$lookup_short = strtolower($this->get_short_name($attribute_name));
		$result = [];

		foreach ($this->attributes['method'] ?? [] as $target_name => $entries) {
			foreach ($entries as $entry) {
				$entry_name = strtolower($entry['attribute'] ?? '');
				$entry_short = strtolower($this->get_short_name($entry['attribute'] ?? ''));

				if ($attribute_name !== '' && $entry_name !== $lookup_full && $entry_short !== $lookup_short) {
					continue;
				}

				$class_name = $entry['class'] ?? '';
				$method_name = $entry['method'] ?? '';

				// support older cache entries by parsing the target when needed
				if (($class_name === '' || $method_name === '') && strpos($target_name, '::') !== false) {
					$parts = explode('::', $target_name, 2);
					$class_name = $parts[0] ?? '';
					$method_name = $parts[1] ?? '';
				}

				if ($class_name === '' || $method_name === '') {
					continue;
				}

				$result[] = [
					'class' => $class_name,
					'method' => $method_name,
					'callable' => [$class_name, $method_name],
					'attribute' => $entry['attribute'] ?? '',
					'arguments' => $entry['arguments'] ?? '',
					'raw' => $entry['raw'] ?? '',
					'target' => $target_name,
					'file' => $entry['file'] ?? '',
					'line' => $entry['line'] ?? 0,
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
		$key = trim($class_name) . '::$' . $property_name;
		return $this->attributes['property'][$key] ?? [];
	}

	/**
	 * Returns the default attribute map shape.
	 *
	 * @return array
	 */
	private function default_attribute_map(): array {
		return [
			'class' => [],
			'interface' => [],
			'trait' => [],
			'function' => [],
			'method' => [],
			'property' => [],
			'constant' => [],
			'class_constant' => [],
		];
	}

	/**
	 * Scans all attribute metadata files and populates the in-memory index.
	 *
	 * @param string $project_path
	 *
	 * @return void
	 */
	private function reload_attributes(string $project_path): void {
		$attribute_search_path = [
			$project_path . '/resources/attributes/*.php',
			$project_path . '/*/*/resources/attributes/*.php',
			$project_path . '/*/*/resources/attributes/*/*.php',
		];

		$attribute_files = [];
		foreach ($attribute_search_path as $path) {
			$attribute_files = array_merge($attribute_files, glob($path));
		}

		$attribute_files = array_unique($attribute_files);
		foreach ($attribute_files as $file) {
			$this->parse_attribute_file($file);
		}
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

		$tokens = token_get_all($file_content);
		$namespace = '';
		$brace_level = 0;
		$current_class = '';
		$next_class = '';
		$class_brace_level = null;
		$expect_class_brace = false;
		$pending_attributes = [];

		$token_count = count($tokens);
		for ($i = 0; $i < $token_count; $i++) {
			$token = $tokens[$i];

			if (is_string($token)) {
				if ($token === '{') {
					$brace_level++;
					if ($expect_class_brace) {
						$current_class = $next_class;
						$class_brace_level = $brace_level;
						$expect_class_brace = false;
					}
				} else if ($token === '}') {
					if ($class_brace_level !== null && $brace_level === $class_brace_level) {
						$current_class = '';
						$class_brace_level = null;
					}
					$brace_level = max(0, $brace_level - 1);
				}
				continue;
			}

			$token_id = $token[0];
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
				$prev_index = $this->next_significant_index($tokens, $i - 1, -1);
				$previous_was_new = ($prev_index !== null && is_array($tokens[$prev_index]) && $tokens[$prev_index][0] === T_NEW);
				if ($previous_was_new) {
					$pending_attributes = [];
					continue;
				}

				$name_index = $this->next_significant_index($tokens, $i + 1, 1);
				if ($name_index !== null && is_array($tokens[$name_index]) && $tokens[$name_index][0] === T_STRING) {
					$short_name = trim($tokens[$name_index][1]);
					$full_name = $namespace !== '' ? $namespace . '\\' . $short_name : $short_name;
					$next_class = $full_name;
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
					$name = trim($tokens[$name_index][1]);
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
				$constant_names = [];
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
						$constant_names[] = $current_token[1];
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
				$target_name = $current_class . '::$' . $property_name;
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
				'attribute' => $attribute['name'] ?? '',
				'arguments' => $attribute['arguments'] ?? '',
				'raw' => $attribute['raw'] ?? '',
				'target_type' => $target_type,
				'target' => $target_name,
				'file' => $file,
				'line' => $line,
			];

			if ($target_type === 'method' && strpos($target_name, '::') !== false) {
				$parts = explode('::', $target_name, 2);
				$entry['class'] = $parts[0] ?? '';
				$entry['method'] = $parts[1] ?? '';
				$entry['is_static'] = in_array('static', $modifiers, true);
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
		$buffer = '';
		$depth = 0;
		$start_line = is_array($tokens[$index]) ? ($tokens[$index][2] ?? 0) : 0;

		for ($j = $index; $j < count($tokens); $j++) {
			$current = $tokens[$j];
			$text = is_array($current) ? $current[1] : $current;
			$buffer .= $text;

			$depth += substr_count($text, '[');
			$depth -= substr_count($text, ']');

			if ($depth <= 0 && strpos($text, ']') !== false) {
				$index = $j;
				break;
			}
		}

		$content = trim($buffer);
		$inside = trim($content, '#[] \t\n\r\0\x0B');
		$parts = $this->split_top_level_csv($inside);

		$result = [];
		foreach ($parts as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}

			$name = $part;
			$arguments = '';
			if (preg_match('/^([\\\\a-zA-Z_][\\\\a-zA-Z0-9_]*)(?:\s*\((.*)\))?$/s', $part, $match)) {
				$name = trim($match[1]);
				$arguments = isset($match[2]) ? trim($match[2]) : '';
			}

			$result[] = [
				'name' => $name,
				'arguments' => $arguments,
				'raw' => $part,
				'line' => $start_line,
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
		$result = [];
		$current = '';
		$paren_depth = 0;
		$bracket_depth = 0;
		$length = strlen($value);

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
				$current = '';
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
	 * Returns true when a token id is part of a namespace name.
	 *
	 * @param int $token_id
	 *
	 * @return bool
	 */
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
			
			$token_id = $token[0];
			$token_text = trim($token[1] ?? '');
			
			// Stop at attribute start
			if ($token_id === T_ATTRIBUTE) {
				break;
			}
			
			// Capture modifier keywords
			if ($token_id === T_STATIC || $token_id === T_PUBLIC || $token_id === T_PROTECTED || 
			    $token_id === T_PRIVATE || $token_id === T_ABSTRACT || $token_id === T_FINAL) {
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
		//make sure we can return values if no classes have been loaded
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
		//make sure we can return values
		if (empty($this->classes) || empty($this->interfaces)) {
			return [];
		}

		//get direct implementers of this interface
		$result = $this->interfaces[$interface_name] ?? [];

		//find all child interfaces (interfaces that extend this interface)
		$child_interfaces = $this->get_child_interfaces($interface_name);

		//for each child interface, get its implementers
		foreach ($child_interfaces as $child_interface) {
			if (!empty($this->interfaces[$child_interface])) {
				$result = array_merge($result, $this->interfaces[$child_interface]);
			}
		}

		//remove duplicates and return
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
	 * The loader is set to private because only the PHP engine should be calling this method
	 *
	 * @param string $class_name The class name that needs to be loaded
	 *
	 * @return bool True if the class is loaded or false when the class is not found
	 * @access private
	 */
	private function loader($class_name): bool {

		//sanitize the class name (preserve backslashes for namespaces)
		$class_name = preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $class_name);

		//find the path using the class_name as the key in the classes array
		if (isset($this->classes[$class_name])) {
			//include the class or interface
			$result = @include_once $this->classes[$class_name];

			//check for edge case where the file was deleted after cache creation
			if ($result === false) {
				//send to syslog when debugging
				self::log(LOG_ERR, "class '$class_name' registered but include failed (file deleted?). Removed from cache.");

				//remove the class from the array
				unset($this->classes[$class_name]);

				if ($this->cache_enabled) {
					//update the cache with new classes
					$this->update_cache();
				}

				//return failure
				return false;
			}

			//return success
			return true;
		}

		//Smarty has it's own autoloader so reject the request
		if ($class_name === 'Smarty_Autoloader') {
			return false;
		}

		//cache miss
		self::log(LOG_WARNING, "class '$class_name' not found in cache");

		//set project path using magic dir constant
		$project_path = $this->project_path();

		//build the search path array
		$search_path = [];
		foreach ($this->class_search_paths($project_path, $class_name) as $path) {
			$search_path[] = glob($path);
		}

		//fix class names in the plugins directory prefixed with 'plugin_'
		if (str_starts_with($class_name, 'plugin_')) {
			$class_name = substr($class_name, 7);
		}
		$search_path[] = glob($project_path . "/core/authentication/resources/classes/plugins/" . $class_name . ".php");

		//collapse all entries to only the matched entry
		$matches = array_filter($search_path);
		if (!empty($matches)) {
			$path = array_pop($matches)[0];

			//include the class, interface, or trait
			include_once $path;

			//inject the class in to the array
			$this->classes[$class_name] = $path;

			if ($this->cache_enabled) {
				//update the cache with new classes
				$this->update_cache();
			}

			//return boolean
			return true;
		}

		//send to syslog when debugging
		self::log(LOG_ERR, "class '$class_name' not found name");

		//return boolean
		return false;
	}

	/**
	 * Resolves the FusionPBX project root directory.
	 *
	 * @return string
	 */
	private function project_path(): string {
		$configured_project_path = $this->project_path_from_env();
		if ($configured_project_path !== null) {
			return $configured_project_path;
		}

		$candidate = dirname(__DIR__, 4);
		if (is_dir($candidate . '/resources/classes')) {
			return $candidate;
		}

		return dirname(__DIR__, 2);
	}

	/**
	 * Resolves the optional class scan root from app/fusor/.env.
	 *
	 * Supported keys:
	 * [auto_loader] scan_path=/path/to/fusionpbx
	 * [classes] scan_path=/path/to/fusionpbx
	 *
	 * @return string|null
	 */
	private function project_path_from_env(): ?string {
		$env = $this->env_settings();
		$value = $env['auto_loader']['scan_path']
			?? ($env['classes']['scan_path'] ?? null)
			?? ($env['auto_loader']['project_path'] ?? ($env['classes']['project_path'] ?? null));

		if (is_array($value)) {
			return null;
		}

		if (!is_string($value)) {
			return null;
		}

		$path = rtrim(trim($value), '/');
		if ($path === '') {
			return null;
		}

		if (is_dir($path . '/resources/classes')) {
			return $path;
		}

		self::log(LOG_WARNING, "invalid .env scan_path '$path' (expected FusionPBX root containing resources/classes)");
		return null;
	}

	/**
	 * Builds class discovery search paths from env config or defaults.
	 *
	 * Supported config:
	 * [auto_loader]
	 * scan_path.0=/resources/interfaces/*.php
	 * scan_path.1=/resources/traits/*.php
	 * ...
	 *
	 * @param string $project_path
	 * @param string $class_name Optional class to target one-file lookups
	 *
	 * @return array
	 */
	private function class_search_paths(string $project_path, string $class_name = ''): array {
		$patterns = $this->configured_search_patterns_from_env();
		if (empty($patterns)) {
			$patterns = [
				'/resources/interfaces/*.php',
				'/resources/traits/*.php',
				'/resources/classes/*.php',
				'/resources/classes/*/*.php',
				'/*/*/resources/interfaces/*.php',
				'/*/*/resources/traits/*.php',
				'/*/*/resources/classes/*.php',
				'/*/*/resources/classes/*/*.php',
				'/core/authentication/resources/classes/plugins/*.php',
			];
		}

		$search_paths = [];
		$normalized_root = rtrim($project_path, '/');
		foreach ($patterns as $pattern) {
			$relative_pattern = $this->normalize_search_pattern($pattern);
			if ($relative_pattern === '') {
				continue;
			}

			$resolved_pattern = $relative_pattern;
			if ($class_name !== '') {
				$resolved_pattern = preg_replace('/\*\.php$/', $class_name . '.php', $relative_pattern) ?? $relative_pattern;
			}

			$search_paths[] = $normalized_root . $resolved_pattern;
		}

		return array_values(array_unique($search_paths));
	}

	/**
	 * Reads indexed scan_path.* patterns from app/fusor/.env.
	 *
	 * @return array
	 */
	private function configured_search_patterns_from_env(): array {
		$env = $this->env_settings();
		$settings = $env['auto_loader'] ?? [];
		if (!is_array($settings)) {
			return [];
		}

		$patterns = [];
		foreach ($settings as $key => $value) {
			if (!is_string($key) || !is_string($value)) {
				continue;
			}

			if (strpos($key, 'scan_path.') !== 0 && strpos($key, 'project_path.') !== 0) {
				continue;
			}

			$prefix = strpos($key, 'scan_path.') === 0 ? 'scan_path.' : 'project_path.';
			$index = (int) substr($key, strlen($prefix));
			$normalized = $this->normalize_search_pattern($value);
			if ($normalized === '') {
				continue;
			}

			$patterns[$index] = $normalized;
		}

		if (empty($patterns)) {
			return [];
		}

		ksort($patterns);
		return array_values($patterns);
	}

	/**
	 * Normalizes one configured search pattern.
	 *
	 * @param string $pattern
	 *
	 * @return string
	 */
	private function normalize_search_pattern(string $pattern): string {
		$normalized = trim($pattern);
		$normalized = rtrim($normalized, ',');
		$normalized = trim($normalized, " \n\r\t\v\x00\"'");
		if ($normalized === '') {
			return '';
		}

		if (!str_starts_with($normalized, '/')) {
			$normalized = '/' . $normalized;
		}

		return $normalized;
	}

	/**
	 * Determines whether class cache is enabled via app/fusor/.env.
	 *
	 * Expected format:
	 * [auto_loader]
	 * cache=true|false
	 *
	 * @return bool
	 */
	private function cache_enabled_from_env(): bool {
		$env = $this->env_settings();

		$value = $env['auto_loader']['cache'] ?? null;
		if ($value === null) {
			return true;
		}

		$normalized = strtolower(trim((string) $value));
		if ($normalized === 'false' || $normalized === '0' || $normalized === 'off' || $normalized === 'no') {
			return false;
		}

		if ($normalized === 'true' || $normalized === '1' || $normalized === 'on' || $normalized === 'yes') {
			return true;
		}

		return true;
	}

	/**
	 * Reads app/fusor/.env settings once per request.
	 *
	 * @return array
	 */
	private function env_settings(): array {
		if (is_array($this->env_settings)) {
			return $this->env_settings;
		}

		$fusor_path = dirname(__DIR__, 2);
		$env_loader_file = $fusor_path . '/resources/classes/env_loader.php';

		if (!class_exists('env_loader', false) && is_file($env_loader_file)) {
			require_once $env_loader_file;
		}

		if (class_exists('env_loader', false)) {
			env_loader::load_env_file($fusor_path);
			env_loader::set_env();
			$env = env_loader::get_settings();
			$this->env_settings = is_array($env) ? $env : [];
			return $this->env_settings;
		}

		$env_file = $fusor_path . '/.env';
		if (!is_file($env_file)) {
			$this->env_settings = [];
			return $this->env_settings;
		}

		$env = @parse_ini_file($env_file, true, INI_SCANNER_RAW);
		$this->env_settings = is_array($env) ? $env : [];

		return $this->env_settings;
	}

	/**
	 * Builds a SAPI-scoped cache file path to avoid CLI/FPM ownership collisions.
	 */
	private static function cache_file_path(string $base_file): string {
		$sapi = preg_replace('/[^a-z0-9_]/i', '_', PHP_SAPI ?: 'unknown');
		$file_name = preg_replace('/\.php$/', '_' . $sapi . '.php', $base_file);

		return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $file_name;
	}
}
