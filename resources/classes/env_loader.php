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
*/

/**
 * Env loader for app/fusor/.env with support for sectioned INI-style values.
 */
class env_loader {

	/**
	 * Raw .env contents.
	 *
	 * @var string
	 */
	private static string $env_text = '';

	/**
	 * Parsed .env settings.
	 *
	 * @var array
	 */
	private static array $env_settings = [];

	/**
	 * Loads and parses .env from the provided directory.
	 *
	 * @param string $directory
	 *
	 * @return void
	 */
	public static function load_env_file(string $directory): void {
		$env_file = rtrim($directory, '/\\');
		if (!is_file($env_file)) {
			self::$env_text = '';
			self::$env_settings = [];
			return;
		}
		self::$env_text = self::load_text_file($env_file);
		self::$env_settings = self::parse_env_text(self::$env_text);
	}

	/**
	 * Loads plain text from a file.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	public static function load_text_file(string $file): string {
		$file_len = @filesize($file);
		if (!is_int($file_len) || $file_len <= 0) {
			return '';
		}

		$handle = null;
		try {
			$handle = @fopen($file, 'r');
			if ($handle === false) {
				return '';
			}

			$contents = @fread($handle, $file_len);
			return is_string($contents) ? $contents : '';
		} catch (Exception $e) {
			return '';
		} finally {
			if (is_resource($handle)) {
				fclose($handle);
			}
		}
	}

	/**
	 * Sets parsed values into $_ENV and process env.
	 *
	 * Section names are ignored so keys are always available as flat
	 * root-level env settings (for example $_ENV['PROJECT_ROOT']).
	 * Existing values (such as CLI-provided env vars) are preserved.
	 *
	 * @return void
	 */
	public static function set_env(): void {
		foreach (self::$env_settings as $key_or_section => $value_or_settings) {
			if (is_array($value_or_settings)) {
				foreach ($value_or_settings as $key => $value) {
					self::set_env_key_value($key, $value);
				}
				continue;
			}

			self::set_env_key_value($key_or_section, $value_or_settings);
		}
	}

	/**
	 * Sets a single env key while preserving CLI/process-provided values.
	 *
	 * @param mixed $key
	 * @param mixed $value
	 *
	 * @return void
	 */
	private static function set_env_key_value($key, $value): void {
		if (!is_string($key) || !is_scalar($value)) {
			return;
		}

		$normalized_value = self::normalize_env_value($key, (string) $value);

		// Collapse dotted keys (e.g. scan_path.0, scan_path.1) into nested arrays.
		if (($dot_pos = strpos($key, '.')) !== false) {
			$parent = substr($key, 0, $dot_pos);
			$child = substr($key, $dot_pos + 1);

			if (getenv($key) !== false) {
				return;
			}

			if (isset($_ENV[$parent]) && !is_array($_ENV[$parent])) {
				return;
			}

			if (!isset($_ENV[$parent]) || !is_array($_ENV[$parent])) {
				$_ENV[$parent] = [];
			}

			if (ctype_digit($child)) {
				$index = (int) $child;
				if (array_key_exists($index, $_ENV[$parent])) {
					return;
				}
				$_ENV[$parent][$index] = $normalized_value;
			} else {
				if (array_key_exists($child, $_ENV[$parent])) {
					return;
				}
				$_ENV[$parent][$child] = $normalized_value;
			}

			@putenv($key . '=' . $normalized_value);
			return;
		}

		if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
			return;
		}

		$_ENV[$key] = $normalized_value;
		@putenv($key . '=' . $normalized_value);
	}

	/**
	 * Returns parsed settings grouped by section.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		return self::$env_settings;
	}

	/**
	 * Parses INI-like .env text with section support.
	 *
	 * @param string $env_text
	 *
	 * @return array
	 */
	private static function parse_env_text(string $env_text): array {
		if (trim($env_text) === '') {
			return [];
		}

		$parsed = @parse_ini_string($env_text, true, INI_SCANNER_RAW);
		if (is_array($parsed)) {
			return $parsed;
		}

		return [];
	}

	/**
	 * Normalizes env values for indexed scan_path settings.
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return string
	 */
	private static function normalize_env_value(string $key, string $value): string {
		if ($key === 'scan_path'
			|| strpos($key, 'scan_path.') === 0
			|| $key === 'project_path'
			|| strpos($key, 'project_path.') === 0
		) {
			$normalized = trim($value);
			$normalized = rtrim($normalized, ',');
			$normalized = trim($normalized, " \n\r\t\v\x00\"'");
			return $normalized;
		}

		return $value;
	}
}
