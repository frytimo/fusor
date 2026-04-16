<?php

declare(strict_types=1);

namespace Frytimo\Fusor\resources\classes;

use Fuz\Component\SharedMemory\Storage\StorageFile;
use Frytimo\Fusor\resources\interfaces\event_relay_listener;

/**
 * Fusor service.
 */
class fusor_service extends \service {

	public const VERSION = '1.0.0';
	private const SHARED_MEMORY_FILE = '/dev/shm/fusor.shared.sync';
	private const SHARED_MEMORY_FALLBACK_FILE = '/tmp/fusor.shared.sync';

	protected static string $switch_address = '127.0.0.1';
	protected static int $switch_port = 9000;
	protected static bool $use_pcntl = true;
	protected static bool $dump_maps = false;

	protected $listen_socket = null;
	protected bool $child_mode = false;

	/** @var array<int, int> */
	protected array $children = [];

	/**
	 * @var array<int, array{socket: resource, buffer: string, connected: bool}>
	 */
	protected array $connections = [];

	/**
	 * @var array<string, array<int, array{callback: callable, args: array<string, mixed>}>>
	 */
	protected array $switch_event_callbacks = [];

	/**
	 * @var array<string, array<int, array{class: class-string<event_relay_listener>, args: array<string, mixed>}>>
	 */
	protected array $switch_event_listener_classes = [];

	/** @var array<string, bool> */
	protected array $listener_fast_index = [];

	/** @var array<string, string> */
	protected array $listener_raw_tokens = [];

	protected bool $has_wildcard_listener = false;

	protected static ?message_queue $shared_memory = null;

	/**
	 * Display version.
	 * @return void
	 */
	protected static function display_version(): void {
		echo "Fusor Service version " . self::VERSION . "\n";
	}

	/**
	 * Set command options.
	 * @return mixed
	 */
	protected static function set_command_options() {
		parent::append_command_option(\command_option::new([
			'short_option' => 'm',
			'long_option' => 'update-maps',
			'description' => 'Reload listener maps for a running service and exit',
			'functions' => ['send_reload', 'shutdown'],
		]));

		parent::append_command_option(\command_option::new([
			'short_option' => '',
			'long_option' => 'switch-address:',
			'description' => 'Set switch listener bind address (default 127.0.0.1)',
			'functions' => ['set_switch_address'],
		]));

		parent::append_command_option(\command_option::new([
			'short_option' => '',
			'long_option' => 'switch-port:',
			'description' => 'Set switch listener bind port (default 9000)',
			'functions' => ['set_switch_port'],
		]));

		parent::append_command_option(\command_option::new([
			'short_option' => '',
			'long_option' => 'no-pcntl',
			'description' => 'Disable PCNTL fork workers and run all socket processing in-process',
			'functions' => ['disable_pcntl'],
		]));

		parent::append_command_option(\command_option::new([
			'short_option' => '',
			'long_option' => 'dump-maps',
			'description' => 'Print the loaded switch listener map at startup',
			'functions' => ['enable_map_dump'],
		]));
	}

	/**
	 * Set switch address.
	 * @param mixed $address
	 * @return void
	 */
	protected static function set_switch_address(string $address): void {
		$address = trim($address);
		if ($address !== '') {
			self::$switch_address = $address;
		}
	}

	/**
	 * Set switch port.
	 * @param mixed $port
	 * @return void
	 */
	protected static function set_switch_port(string $port): void {
		$parsed_port = (int) $port;
		if ($parsed_port > 0 && $parsed_port <= 65535) {
			self::$switch_port = $parsed_port;
		}
	}

	/**
	 * Disable pcntl.
	 * @return void
	 */
	protected static function disable_pcntl(): void {
		self::$use_pcntl = false;
	}

	/**
	 * Enable map dump.
	 * @return void
	 */
	protected static function enable_map_dump(): void {
		self::$dump_maps = true;
	}

	/**
	 * Run.
	 * @return int
	 */
	public function run(): int {
		$this->reload_settings();

		$this->listen_socket = @stream_socket_server(
			"tcp://" . self::$switch_address . ':' . self::$switch_port,
			$errno,
			$errstr
		);

		if (!is_resource($this->listen_socket)) {
			throw new \RuntimeException("Unable to bind Fusor switch listener on " . self::$switch_address . ':' . self::$switch_port . " ({$errno}): {$errstr}");
		}

		stream_set_blocking($this->listen_socket, false);
		$this->notice('Fusor switch listener active on ' . self::$switch_address . ':' . self::$switch_port);

		if ($this->pcntl_enabled()) {
			pcntl_signal(SIGCHLD, [$this, 'handle_sigchld']);
		}

		while ($this->running) {
			$read = [$this->listen_socket];
			foreach ($this->connections as $connection) {
				if (isset($connection['socket']) && is_resource($connection['socket'])) {
					$read[] = $connection['socket'];
				}
			}

			$write = $except = [];
			$changed = @stream_select($read, $write, $except, 1);
			if ($changed === false) {
				$this->warning('stream_select failed; pruning dead sockets');
				$this->prune_connections();
				continue;
			}

			if ($changed === 0) {
				continue;
			}

			if (in_array($this->listen_socket, $read, true)) {
				$this->accept_connection($this->listen_socket);
				$listen_index = array_search($this->listen_socket, $read, true);
				if ($listen_index !== false) {
					unset($read[$listen_index]);
				}
			}

			foreach ($read as $socket) {
				try {
					$this->handle_connection_data($socket);
				} catch (\socket_disconnected_exception $exception) {
					$this->debug('Socket disconnected: ' . $exception->getMessage());
				}
			}
		}

		$this->shutdown_listener_socket();
		$this->notice('Fusor switch listener stopped');
		return 0;
	}

	/**
	 * Reload settings.
	 * @return void
	 */
	protected function reload_settings(): void {
		parent::$config->read();

		$this->initialize_shared_memory();
		$this->rebuild_listener_maps();
		$this->publish_listener_map_to_shared_memory();

		if (self::$dump_maps) {
			$this->notice('Listener fast-map: ' . implode(', ', array_keys($this->listener_fast_index)));
		}

		$this->notice('Fusor listener maps reloaded');
	}

	/**
	 * Initialize shared memory.
	 * @return void
	 */
	protected function initialize_shared_memory(): void {
		if (self::$shared_memory instanceof message_queue) {
			return;
		}

		$this->ensure_composer_autoload();

		$storage_path = self::SHARED_MEMORY_FILE;
		$storage_dir = dirname($storage_path);
		if (!is_dir($storage_dir) || !is_writable($storage_dir)) {
			$storage_path = self::SHARED_MEMORY_FALLBACK_FILE;
		}

		try {
			self::$shared_memory = new message_queue(new StorageFile($storage_path));
		} catch (\Throwable $throwable) {
			self::$shared_memory = null;
			$this->warning('Unable to initialize shared memory queue: ' . $throwable->getMessage());
		}
	}

	/**
	 * Ensure composer autoload.
	 * @return void
	 */
	protected function ensure_composer_autoload(): void {
		if (class_exists('Fuz\\Component\\SharedMemory\\SharedMemory')) {
			return;
		}

		$autoload_candidates = array_values(array_unique([
			FUSOR_DIR . '/vendor/autoload.php',
			PROJECT_ROOT_DIR . '/vendor/autoload.php',
			dirname(PROJECT_ROOT_DIR) . '/vendor/autoload.php',
		]));

		foreach ($autoload_candidates as $candidate) {
			if (!is_file($candidate)) {
				continue;
			}

			require_once $candidate;
			if (class_exists('Fuz\\Component\\SharedMemory\\SharedMemory')) {
				return;
			}
		}
	}

	/**
	 * Publish listener map to shared memory.
	 * @return void
	 */
	protected function publish_listener_map_to_shared_memory(): void {
		if (!(self::$shared_memory instanceof message_queue)) {
			return;
		}

		try {
			self::$shared_memory->switch_listener_fast_index = array_keys($this->listener_fast_index);
			self::$shared_memory->switch_listener_raw_tokens = $this->listener_raw_tokens;
			self::$shared_memory->switch_listener_count = count($this->listener_fast_index);
			self::$shared_memory->switch_listener_reloaded_epoch = time();
		} catch (\Throwable $throwable) {
			$this->warning('Unable to write listener map to shared memory: ' . $throwable->getMessage());
		}
	}

	/**
	 * Rebuild listener maps.
	 * @return void
	 */
	protected function rebuild_listener_maps(): void {
		$this->switch_event_callbacks = [];
		$this->switch_event_listener_classes = [];
		$this->listener_fast_index = [];
		$this->listener_raw_tokens = [];
		$this->has_wildcard_listener = false;

		$autoload = $this->get_autoload();
		$this->load_attribute_switch_listeners($autoload);
		$this->load_interface_switch_listeners($autoload);
	}

	/**
	 * Get autoload.
	 * @return ?\auto_loader
	 */
	protected function get_autoload(): ?\auto_loader {
		global $autoload;

		if (isset($autoload) && $autoload instanceof \auto_loader) {
			return $autoload;
		}

		$autoload_file = PROJECT_ROOT_DIR . '/resources/classes/auto_loader.php';
		if (!is_file($autoload_file)) {
			$autoload_file = FUSOR_DIR . '/resources/classes/auto_loader.php';
		}
		if (!is_file($autoload_file)) {
			return null;
		}

		require_once $autoload_file;
		$autoload = new \auto_loader();
		return $autoload;
	}

	/**
	 * Load attribute switch listeners.
	 * @param mixed $autoload
	 * @return void
	 */
	protected function load_attribute_switch_listeners(?\auto_loader $autoload): void {
		if (!$autoload instanceof \auto_loader || !class_exists(fusor_discovery::class) || !method_exists($autoload, 'get_attributes')) {
			$this->load_attribute_switch_listeners_by_reflection();
			return;
		}

		fusor_discovery::discover_attributes($autoload, true);
		$methods = fusor_discovery::get_methods('on');
		if (empty($methods)) {
			$this->load_attribute_switch_listeners_by_reflection();
			return;
		}

		$prioritized_callbacks = [];

		foreach ($methods as $method_entry) {
			if (!is_array($method_entry)) {
				continue;
			}

			$class_name = trim((string) ($method_entry['class'] ?? ''));
			$method_name = trim((string) ($method_entry['method'] ?? ''));
			if ($class_name === '' || $method_name === '') {
				continue;
			}

			if (!class_exists($class_name)) {
				continue;
			}

			$arguments = $method_entry['arguments'] ?? [];
			if (!is_array($arguments)) {
				$arguments = [];
			}

			$fusor_event_name = trim((string) ($arguments['event_name'] ?? ''));
			$priority = (int) ($arguments['priority'] ?? 0);
			$this->register_switch_attribute_listener($class_name, $method_name, $fusor_event_name, $priority, $prioritized_callbacks);
		}

		foreach ($prioritized_callbacks as $event_name => $priorities) {
			krsort($priorities, SORT_NUMERIC);
			$this->switch_event_callbacks[$event_name] = [];
			foreach ($priorities as $listeners) {
				foreach ($listeners as $listener) {
					$this->switch_event_callbacks[$event_name][] = $listener;
				}
			}
		}
	}

	/**
	 * Load attribute switch listeners by reflection.
	 * @return void
	 */
	protected function load_attribute_switch_listeners_by_reflection(): void {
		$attribute_class = '\\frytimo\\fusor\\resources\\attributes\\on';
		$attribute_lookup = ltrim(strtolower($attribute_class), '\\');

		$this->include_fusor_class_files_for_discovery();

		$prioritized_callbacks = [];
		foreach ($this->discover_fusor_classes() as $class_name) {
			if (!class_exists($class_name)) {
				continue;
			}

			try {
				$reflection = new \ReflectionClass($class_name);
			} catch (\ReflectionException $exception) {
				unset($exception);
				continue;
			}

			foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
				if (!$method->isStatic()) {
					continue;
				}

				foreach ($method->getAttributes() as $attribute) {
					$attribute_name = ltrim(strtolower((string) $attribute->getName()), '\\');
					if ($attribute_name !== $attribute_lookup) {
						continue;
					}

					$arguments = $attribute->getArguments();
					$fusor_event_name = trim((string) ($arguments['event_name'] ?? $arguments[0] ?? ''));
					$priority = (int) ($arguments['priority'] ?? $arguments[1] ?? 0);
					$this->register_switch_attribute_listener($class_name, $method->getName(), $fusor_event_name, $priority, $prioritized_callbacks);
				}
			}
		}

		foreach ($prioritized_callbacks as $event_name => $priorities) {
			krsort($priorities, SORT_NUMERIC);
			$this->switch_event_callbacks[$event_name] = [];
			foreach ($priorities as $listeners) {
				foreach ($listeners as $listener) {
					$this->switch_event_callbacks[$event_name][] = $listener;
				}
			}
		}
	}

	/**
	 * Include fusor class files for discovery.
	 * @return void
	 */
	protected function include_fusor_class_files_for_discovery(): void {
		$class_files = array_merge(
			glob(dirname(__DIR__) . '/classes/*.php') ?: [],
			glob(dirname(__DIR__) . '/classes/*/*.php') ?: []
		);

		foreach (array_unique($class_files) as $class_file) {
			if (!is_file($class_file) || !$this->is_namespaced_fusor_class_file($class_file)) {
				continue;
			}

			require_once $class_file;
		}
	}

	/**
	 * Is namespaced fusor class file.
	 * @param mixed $file_path
	 * @return bool
	 */
	protected function is_namespaced_fusor_class_file(string $file_path): bool {
		$contents = @file_get_contents($file_path);
		if (!is_string($contents) || $contents === '') {
			return false;
		}

		return preg_match('/^\s*namespace\s+frytimo\\\\fusor\\\\resources\\\\classes(?:\\\\[a-zA-Z0-9_\\\\]+)?\s*;/im', $contents) === 1;
	}

	/**
	 * @return array<int, string>
	 */
	protected function discover_fusor_classes(): array {
		$prefix = 'frytimo\\fusor\\resources\\classes\\';
		$class_root = str_replace('\\\\', '/', dirname(__DIR__) . '/classes/');
		$classes = [];

		foreach (get_declared_classes() as $class_name) {
			if (!str_starts_with(strtolower($class_name), $prefix)) {
				continue;
			}

			try {
				$reflection = new \ReflectionClass($class_name);
			} catch (\ReflectionException $exception) {
				unset($exception);
				continue;
			}

			$file_name = $reflection->getFileName();
			if (!is_string($file_name) || $file_name === '') {
				continue;
			}

			$normalized_file = str_replace('\\\\', '/', $file_name);
			if (!str_starts_with($normalized_file, $class_root)) {
				continue;
			}

			$classes[] = $class_name;
		}

		sort($classes, SORT_STRING);
		return array_values(array_unique($classes));
	}

	/**
	 * @param array<string, array<int, array{callback: callable, args: array<string, mixed>}>> $prioritized_callbacks
	 */
	protected function register_switch_attribute_listener(string $class_name, string $method_name, string $fusor_event_name, int $priority, array &$prioritized_callbacks): void {
		if ($fusor_event_name === '' || stripos($fusor_event_name, 'switch.') !== 0) {
			return;
		}

		$switch_token = substr($fusor_event_name, 7);
		if (!is_string($switch_token)) {
			return;
		}

		$switch_token = trim($switch_token);
		if ($switch_token === '') {
			return;
		}

		$canonical_event_name = strtoupper(str_replace('.', '::', $switch_token));
		$service = $this;
		$callback = static function (array $event, array $args, string $event_name, $client) use ($class_name, $method_name, $fusor_event_name, $service): void {
			$fusor_event = new fusor_event($fusor_event_name, data: [
				'event' => $event,
				'args' => $args,
				'event_name' => $event_name,
				'client' => $client,
				'service' => $service,
			]);
			$class_name::$method_name($fusor_event);
		};

		$prioritized_callbacks[$canonical_event_name][$priority][] = [
			'callback' => $callback,
			'args' => [
				'fusor_event_name' => $fusor_event_name,
				'source' => 'attribute',
			],
		];

		$this->index_listener_event($canonical_event_name, $switch_token);
	}

	/**
	 * Load interface switch listeners.
	 * @param mixed $autoload
	 * @return void
	 */
	protected function load_interface_switch_listeners(?\auto_loader $autoload): void {
		if (!$autoload instanceof \auto_loader || !method_exists($autoload, 'get_interface_list')) {
			return;
		}

		$interface = event_relay_listener::class;
		$listeners = $autoload->get_interface_list($interface);
		if (!is_array($listeners)) {
			return;
		}

		foreach ($listeners as $listener_class) {
			if (!is_string($listener_class) || trim($listener_class) === '') {
				continue;
			}
			$this->register_event_listener_class($listener_class);
		}
	}

	/**
	 * Index listener event.
	 * @param mixed $canonical_event_name
	 * @param mixed $raw_event_name
	 * @return void
	 */
	protected function index_listener_event(string $canonical_event_name, string $raw_event_name): void {
		$this->listener_fast_index[$canonical_event_name] = true;
		$this->listener_raw_tokens[$canonical_event_name] = $raw_event_name !== '' ? $raw_event_name : $canonical_event_name;
		if ($canonical_event_name === '*') {
			$this->has_wildcard_listener = true;
		}
	}

	/**
	 * Pcntl enabled.
	 * @return bool
	 */
	protected function pcntl_enabled(): bool {
		return self::$use_pcntl && function_exists('pcntl_fork') && function_exists('pcntl_waitpid') && function_exists('pcntl_signal');
	}

	/**
	 * Handle sigchld.
	 * @param mixed $signal
	 * @return void
	 */
	public function handle_sigchld(int $signal): void {
		unset($signal);
		while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
			unset($this->children[$pid]);
			$this->debug("Reaped worker pid {$pid} status {$status}");
		}
	}

	/**
	 * Accept connection.
	 * @param mixed $server_socket
	 * @return void
	 */
	public function accept_connection($server_socket): void {
		$client = @stream_socket_accept($server_socket, 0);
		if (!is_resource($client)) {
			return;
		}

		if ($this->pcntl_enabled() && !$this->child_mode) {
			$this->fork_connection_worker($client);
			return;
		}

		$this->attach_connection($client, false);
	}

	/**
	 * Fork connection worker.
	 * @param mixed $client
	 * @return void
	 */
	protected function fork_connection_worker($client): void {
		$pid = pcntl_fork();
		if ($pid === -1) {
			$this->warning('pcntl_fork failed; using in-process listener handling');
			$this->attach_connection($client, false);
			return;
		}

		if ($pid > 0) {
			$this->children[$pid] = time();
			@fclose($client);
			return;
		}

		$this->child_mode = true;
		$this->children = [];

		if (is_resource($this->listen_socket)) {
			@fclose($this->listen_socket);
		}

		$this->attach_connection($client, true);
		$this->run_connection_worker_loop($client);
		exit(0);
	}

	/**
	 * Attach connection.
	 * @param mixed $client
	 * @param mixed $blocking
	 * @return void
	 */
	protected function attach_connection($client, bool $blocking): void {
		stream_set_blocking($client, $blocking);
		$id = (int) $client;
		$this->connections[$id] = [
			'socket' => $client,
			'buffer' => '',
			'connected' => true,
		];

		$this->send_command($client, 'connect');
		$this->notice('Switch connected on socket ' . $id);
	}

	/**
	 * Run connection worker loop.
	 * @param mixed $client
	 * @return void
	 */
	protected function run_connection_worker_loop($client): void {
		while (is_resource($client)) {
			try {
				$this->handle_connection_data($client);
			} catch (\socket_disconnected_exception $exception) {
				$this->debug('Worker socket disconnected: ' . $exception->getMessage());
				break;
			} catch (\Throwable $throwable) {
				$this->warning('Worker loop exception: ' . $throwable->getMessage());
				$this->disconnect($client);
				break;
			}
		}
	}

	/**
	 * Handle connection data.
	 * @param mixed $client
	 * @return void
	 */
	public function handle_connection_data($client): void {
		$id = (int) $client;
		if (!isset($this->connections[$id])) {
			throw new \socket_disconnected_exception($client);
		}

		$data = @fread($client, 8192);
		if ($data === false || ($data === '' && feof($client))) {
			$this->disconnect($client);
			throw new \socket_disconnected_exception($client);
		}

		if ($data === '') {
			return;
		}

		$this->connections[$id]['buffer'] = (string) ($this->connections[$id]['buffer'] ?? '');
		$this->connections[$id]['buffer'] .= $data;

		while (isset($this->connections[$id])) {
			$buffer = &$this->connections[$id]['buffer'];
			$message = $this->extract_message($buffer);
			if ($message === null) {
				break;
			}

			$this->process_message($client, $message);

			if (!isset($this->connections[$id])) {
				break;
			}
		}
	}

	/**
	 * Extract message.
	 * @param mixed $buffer
	 * @return ?array
	 */
	protected function extract_message(string &$buffer): ?array {
		$header_end = strpos($buffer, "\n\n");
		$delimiter_length = 2;

		if ($header_end === false) {
			$header_end = strpos($buffer, "\r\n\r\n");
			$delimiter_length = 4;
		}

		if ($header_end === false) {
			return null;
		}

		$raw_header = substr($buffer, 0, $header_end);
		$remaining = substr($buffer, $header_end + $delimiter_length);
		$headers = $this->parse_headers($raw_header);
		$content_length = isset($headers['Content-Length']) ? (int) $headers['Content-Length'] : 0;

		if (strlen($remaining) < $content_length) {
			return null;
		}

		$body = '';
		if ($content_length > 0) {
			$body = substr($remaining, 0, $content_length);
			$remaining = substr($remaining, $content_length);
		}

		$buffer = (string) $remaining;
		return [
			'headers' => $headers,
			'body' => $body,
			'raw_header' => $raw_header,
		];
	}

	/**
	 * Parse headers.
	 * @param mixed $raw_header
	 * @return array
	 */
	protected function parse_headers(string $raw_header): array {
		$headers = [];
		$lines = preg_split("/\r?\n/", $raw_header) ?: [];
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$parts = explode(':', $line, 2);
			if (count($parts) !== 2) {
				continue;
			}
			$headers[trim($parts[0])] = trim($parts[1]);
		}
		return $headers;
	}

	/**
	 * Parse event body.
	 * @param mixed $body
	 * @return array
	 */
	protected function parse_event_body(string $body): array {
		$event = [];
		$lines = preg_split("/\r?\n/", $body) ?: [];
		foreach ($lines as $line) {
			if ($line === '') {
				continue;
			}
			$parts = explode(':', $line, 2);
			if (count($parts) !== 2) {
				continue;
			}
			$event[trim($parts[0])] = trim(urldecode($parts[1]));
		}
		return $event;
	}

	/**
	 * Process message.
	 * @param mixed $client
	 * @param mixed $message
	 * @return void
	 */
	protected function process_message($client, array $message): void {
		$headers = $message['headers'];
		$body = $message['body'];
		$content_type = $headers['Content-Type'] ?? '';
		$reply_text = trim((string) ($headers['Reply-Text'] ?? ''));

		switch ($content_type) {
			case 'auth/request':
				$this->send_command($client, 'auth ClueCon');
				break;
			case 'command/reply':
				$this->debug('Command reply: ' . ($reply_text !== '' ? $reply_text : trim($body)));
				break;
			case 'api/response':
				$this->debug('API response: ' . ($reply_text !== '' ? $reply_text : trim($body)));
				break;
			case 'text/event-plain':
				$event = $this->parse_event_body($body);
				$event_name = $this->resolve_event_name($event);
				if ($event_name !== '' && (isset($this->listener_fast_index[$event_name]) || $this->has_wildcard_listener)) {
					$this->trigger_switch_event($event_name, $event, $client);
				}
				break;
			case 'text/disconnect-notice':
				$this->disconnect($client);
				break;
			default:
				break;
		}

		$this->maybe_send_initial_commands($client, $headers);
	}

	/**
	 * Resolve event name.
	 * @param mixed $event
	 * @return string
	 */
	protected function resolve_event_name(array $event): string {
		$event_name = trim((string) ($event['Event-Name'] ?? ''));
		if ($event_name === '' && isset($event['Event-Subclass'])) {
			$event_name = trim((string) $event['Event-Subclass']);
		}
		return strtoupper($event_name);
	}

	/**
	 * Trigger switch event.
	 * @param mixed $event_name
	 * @param mixed $event
	 * @param mixed $client
	 * @return void
	 */
	protected function trigger_switch_event(string $event_name, array $event, $client): void {
		if (isset($this->switch_event_callbacks[$event_name])) {
			foreach ($this->switch_event_callbacks[$event_name] as $listener) {
				try {
					call_user_func($listener['callback'], $event, $listener['args'], $event_name, $client);
				} catch (\Throwable $throwable) {
					$this->warning("Switch callback failure for {$event_name}: " . $throwable->getMessage());
				}
			}
		}

		if (isset($this->switch_event_callbacks['*'])) {
			foreach ($this->switch_event_callbacks['*'] as $listener) {
				try {
					call_user_func($listener['callback'], $event, $listener['args'], $event_name, $client);
				} catch (\Throwable $throwable) {
					$this->warning("Switch callback failure for {$event_name}: " . $throwable->getMessage());
				}
			}
		}

		if (isset($this->switch_event_listener_classes[$event_name])) {
			foreach ($this->switch_event_listener_classes[$event_name] as $registration) {
				$this->invoke_class_listener($registration, $event_name, $event, $client);
			}
		}

		if (isset($this->switch_event_listener_classes['*'])) {
			foreach ($this->switch_event_listener_classes['*'] as $registration) {
				$this->invoke_class_listener($registration, $event_name, $event, $client);
			}
		}
	}

	/**
	 * @param array{class: class-string<event_relay_listener>, args: array<string, mixed>} $registration
	 */
	protected function invoke_class_listener(array $registration, string $event_name, array $event, $client): void {
		$listener_class = $registration['class'];
		$listener_args = $registration['args'] ?? [];
		$uuid = (string) ($event['Unique-ID'] ?? '');
		$context = [
			'args' => $listener_args,
			'client' => $client,
			'service' => $this,
			'event_name' => $event_name,
		];

		try {
			$instance = new $listener_class($uuid, $event, $context);
			$instance->event_triggered();
		} catch (\Throwable $throwable) {
			$this->warning("Switch listener class failure for {$event_name}: " . $throwable->getMessage());
		}
	}

	/**
	 * Register event listener class.
	 * @param mixed $listener_class
	 * @param mixed $args
	 * @return void
	 */
	public function register_event_listener_class(string $listener_class, array $args = []): void {
		if (!class_exists($listener_class)) {
			return;
		}

		if (!is_subclass_of($listener_class, event_relay_listener::class)) {
			return;
		}

		$raw_event_name = trim((string) $listener_class::register_event_name());
		$event_name = strtoupper($raw_event_name);
		if ($event_name === '') {
			return;
		}

		if (!isset($this->switch_event_listener_classes[$event_name])) {
			$this->switch_event_listener_classes[$event_name] = [];
		}

		$this->switch_event_listener_classes[$event_name][] = [
			'class' => $listener_class,
			'args' => $args,
		];
		$this->index_listener_event($event_name, $raw_event_name);
	}

	/**
	 * Maybe send initial commands.
	 * @param mixed $client
	 * @param mixed $headers
	 * @return void
	 */
	protected function maybe_send_initial_commands($client, array $headers): void {
		static $initialized = [];
		$id = (int) $client;
		if (isset($initialized[$id])) {
			return;
		}

		$content_type = $headers['Content-Type'] ?? '';
		if (!in_array($content_type, ['text/event-plain', 'command/reply'], true)) {
			return;
		}

		$initialized[$id] = true;
		$this->send_command($client, 'myevents');
		$this->send_preloaded_event_subscriptions($client);
	}

	/**
	 * Send preloaded event subscriptions.
	 * @param mixed $client
	 * @return void
	 */
	protected function send_preloaded_event_subscriptions($client): void {
		if ($this->has_wildcard_listener) {
			$this->send_command($client, 'event plain ALL');
			return;
		}

		$standard_events = [];
		$custom_events = [];

		foreach ($this->listener_raw_tokens as $canonical_event_name => $raw_event_name) {
			if ($canonical_event_name === '*') {
				continue;
			}

			$token = trim($raw_event_name);
			if ($token === '') {
				continue;
			}

			if (str_contains($token, '::')) {
				$custom_events[] = $token;
				continue;
			}

			$standard_events[] = strtoupper($token);
		}

		$standard_events = array_values(array_unique($standard_events));
		$custom_events = array_values(array_unique($custom_events));

		if (!empty($standard_events)) {
			$this->send_command($client, 'event plain ' . implode(' ', $standard_events));
		}

		foreach ($custom_events as $custom_event) {
			$this->send_command($client, 'event plain CUSTOM ' . $custom_event);
		}

		if (empty($standard_events) && empty($custom_events)) {
			$this->send_command($client, 'event plain DTMF');
		}
	}

	/**
	 * Send command.
	 * @param mixed $client
	 * @param mixed $command
	 * @return void
	 */
	protected function send_command($client, string $command): void {
		if (!is_resource($client)) {
			return;
		}

		@fwrite($client, $command . "\n\n");
	}

	/**
	 * Execute app.
	 * @param mixed $client
	 * @param mixed $channel_uuid
	 * @param mixed $application
	 * @param mixed $arguments
	 * @return void
	 */
	public function execute_app($client, string $channel_uuid, string $application, string $arguments = ''): void {
		$channel_uuid = trim($channel_uuid);
		$application = trim($application);
		if ($channel_uuid === '' || $application === '') {
			$this->warning('execute_app called without channel_uuid or application');
			return;
		}

		$lines = [
			"sendmsg {$channel_uuid}",
			'call-command: execute',
			"execute-app-name: {$application}",
		];
		if ($arguments !== '') {
			$lines[] = "execute-app-arg: {$arguments}";
		}
		$this->send_command($client, implode("\n", $lines));
	}

	/**
	 * Prune connections.
	 * @return void
	 */
	protected function prune_connections(): void {
		foreach ($this->connections as $id => $connection) {
			if (!isset($connection['socket']) || !is_resource($connection['socket'])) {
				unset($this->connections[$id]);
			}
		}
	}

	/**
	 * Disconnect.
	 * @param mixed $client
	 * @return void
	 */
	protected function disconnect($client): void {
		$id = (int) $client;
		unset($this->connections[$id]);
		if (is_resource($client)) {
			@fclose($client);
		}
	}

	/**
	 * Shutdown listener socket.
	 * @return void
	 */
	protected function shutdown_listener_socket(): void {
		if (is_resource($this->listen_socket)) {
			@fclose($this->listen_socket);
		}
	}
}

