<?php

namespace Frytimo\Fusor\resources\classes;

/**
 * Fusor logger for syslog and file-based logging.
 *
 * Logs are written to syslog and optionally to a file path
 * defined in the Fusor .env configuration.
 */
class fusor_logger {
	private const SYSLOG_IDENT = 'FusionPBX Fusor';
	private const SYSLOG_FACILITY = LOG_USER;

	private static bool $syslog_opened = false;
	private static ?string $log_file = null;
	private static bool $initialized = false;

	/**
	 * Initialize the logger with configuration from .env.
	 *
	 * @return void
	 */
	public static function initialize(): void {
		if (self::$initialized) {
			return;
		}

		self::$initialized = true;

		// Load log file path from environment
		self::$log_file = self::get_log_file_path();

		// Open syslog connection
		if (!self::$syslog_opened) {
			openlog(self::SYSLOG_IDENT, LOG_NDELAY | LOG_PID, self::SYSLOG_FACILITY);
			self::$syslog_opened = true;
		}
	}

	/**
	 * Get the configured log file path from .env or defaults.
	 *
	 * @return string|null
	 */
	private static function get_log_file_path(): ?string {
		// Try environment variable first (uppercase with underscore)
		$path = getenv('FUSOR_LOG_FILE');
		if ($path !== false && trim($path) !== '') {
			$path = trim($path);
			if ($path !== '/dev/null') {
				return $path;
			}
			return null;
		}

		// Try $_ENV array from .env parsing (section.key format)
		if (isset($_ENV['log_file']) && is_string($_ENV['log_file'])) {
			$path = trim($_ENV['log_file']);
			if ($path !== '' && $path !== '/dev/null') {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Log an info-level message.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function info(string $message): void {
		self::log(LOG_INFO, $message);
	}

	/**
	 * Log a warning-level message.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function warning(string $message): void {
		self::log(LOG_WARNING, $message);
	}

	/**
	 * Log an error-level message.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function error(string $message): void {
		self::log(LOG_ERR, $message);
	}

	/**
	 * Log a message at the specified priority level.
	 *
	 * @param int    $priority
	 * @param string $message
	 *
	 * @return void
	 */
	private static function log(int $priority, string $message): void {
		self::initialize();

		// Write to syslog
		if (self::$syslog_opened) {
			syslog($priority, '[fusor] ' . $message);
		}

		// Write to file if configured
		if (self::$log_file !== null) {
			self::write_log_file($priority, $message);
		}
	}

	/**
	 * Write log entry to file.
	 *
	 * @param int    $priority
	 * @param string $message
	 *
	 * @return void
	 */
	private static function write_log_file(int $priority, string $message): void {
		$priority_name = self::get_priority_name($priority);
		$timestamp = date('Y-m-d H:i:s');
		$pid = getmypid() ?: 'unknown';
		$log_line = "[{$timestamp}] [{$priority_name}] [pid {$pid}] {$message}" . PHP_EOL;

		$handle = @fopen(self::$log_file, 'a');
		if (is_resource($handle)) {
			@fwrite($handle, $log_line);
			@fclose($handle);
		}
	}

	/**
	 * Get the priority level name.
	 *
	 * @param int $priority
	 *
	 * @return string
	 */
	private static function get_priority_name(int $priority): string {
		return match ($priority) {
			LOG_EMERG => 'EMERG',
			LOG_ALERT => 'ALERT',
			LOG_CRIT => 'CRIT',
			LOG_ERR => 'ERROR',
			LOG_WARNING => 'WARNING',
			LOG_NOTICE => 'NOTICE',
			LOG_INFO => 'INFO',
			LOG_DEBUG => 'DEBUG',
			default => 'UNKNOWN',
		};
	}

	/**
	 * Close syslog connection.
	 *
	 * @return void
	 */
	public static function close(): void {
		if (self::$syslog_opened) {
			closelog();
			self::$syslog_opened = false;
		}
	}
}
