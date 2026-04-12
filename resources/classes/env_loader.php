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
		$env_file = rtrim($directory, '/\\') . '/.env';
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
	 * @return void
	 */
	public static function set_env(): void {
		foreach (self::$env_settings as $section => $settings) {
			if (!is_array($settings)) {
				continue;
			}

			foreach ($settings as $key => $value) {
				if (!is_string($key) || !is_scalar($value)) {
					continue;
				}

				$normalized_value = self::normalize_env_value($key, (string) $value);
				$env_key = $section . '.' . $key;
				$_ENV[$env_key] = $normalized_value;
				@putenv($env_key . '=' . $normalized_value);
			}
		}
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
