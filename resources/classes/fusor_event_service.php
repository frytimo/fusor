<?php
namespace Frytimo\Fusor\resources\classes;

use event_socket;
use Frytimo\Fusor\resources\classes\message_queue;
use service;

/**
 * Fusor event service.
 */
class fusor_event_service extends service {
	const FUSOR_VERSION             = '1.0.0';
	const SUPERVISOR_EXIT           = '!EXIT!';
	const SUPERVISOR_RELOAD_PLUGINS = '!RELOAD_PLUGINS!';
	const PERSISTENT_STORAGE_FILE   = '/dev/shm/shared.sync';
	const PERSISTENT                = 'PERSISTENT';

	private static array $listeners = [];

	private static bool $child_running = true;

	protected static message_queue $message_queue;

	/**
	 * Set command options.
	 * @return mixed
	 */
	protected static function set_command_options() {}

	/**
	 * Display version.
	 * @return void
	 */
	protected static function display_version(): void {
		echo "Fusor Event Service version " . self::FUSOR_VERSION . "\n";
	}

	/**
	 * Run.
	 * @return int
	 */
	public function run(): int {
		return 0;
	}

	/**
	 * Reload settings.
	 * @return void
	 */
	protected function reload_settings(): void {
		self::load_plugins();
	}

	/**
	 * Load plugins.
	 * @return mixed
	 */
	public static function load_plugins() {
		self::$listeners = [];
		$files           = glob(__DIR__ . '/events/*.php');
		foreach ($files as $file) {
			if ($file !== __DIR__ . '/events/event_relay_listener.php') {
				include $file;
				$basename  = basename($file, '.php');
				$classname = '\\Frytimo\\Fusor\\resources\\classes\\Events\\' . $basename;
				self::register_listener($classname);
			}
		}
	}

	/**
	 * Register listener.
	 * @param mixed $eventRelayListenerClass
	 * @return mixed
	 */
	public static function register_listener(string $eventRelayListenerClass) {
		print "Registering {$eventRelayListenerClass} for event '{$eventRelayListenerClass::registerEventName()}'\n";
		self::$listeners[$eventRelayListenerClass::registerEventName()][] = $eventRelayListenerClass;
	}

	/**
	 * Run child.
	 * @param mixed $sleep_time
	 * @return mixed
	 */
	private static function run_child(int $sleep_time = 3) {
		$mq = self::$message_queue;
		$mq->set('child_pid', posix_getpid());
		$count = 0;
		static::load_plugins();
		while (static::$child_running) {
			$servers                    = [];
			$controller                 = 'localhost, 8021, ClueCon';
			list($addr, $port, $pass)   = explode(',', $controller);
			$_ENV['controller']['addr'] = trim($addr);
			$_ENV['controller']['port'] = trim($port);
			$_ENV['controller']['pass'] = trim($pass);
			$esl                        = event_socket::create(trim($addr), trim($port), trim($pass));
			if ($esl->connected()) {
				$count = 0;

				stream_set_blocking($esl->fp, true);
				$response = $esl->request("event json all");
				stream_set_blocking($esl->fp, false);

				while (static::$child_running && $esl->connected()) {
					$event             = $esl->read_event();
					$arr               = json_decode($event->serialize('json'), true);
					$uuid              = new Uuid();
					$arr['servers']    = $servers;
					$arr['controller'] = $controller;
					$event_name        = strtolower($arr['Event-Name']);
					if (isset($arr['API-Command']))
						$event_name = strtolower($arr['API-Command']);
					if (isset($arr['Event-Subclass']))
						$event_name = strtolower($arr['Event-Subclass']);
					print "Event Name: {$event_name}";
					$body = $event->getBody();
					if ($body !== null) {
						$arr['BODY'] = $body;
					}
					if (isset(static::$listeners[$event_name])) {
						print " Launching handler\n";
						try {
							static::event_trigger($event_name, $uuid, $arr);
						} catch (\Exception $ex) {
						}
					} else {
						print " No handler\n";
					}
					\sleep($sleep_time);
				}
			}
		}
	}

	/**
	 * Signal handler.
	 * @param mixed $signal
	 * @return mixed
	 */
	public static function signal_handler(int $signal) {
		switch ($signal) {
			case SIGUSR1:
			case SIGTERM:
				posix_kill(self::$message_queue->server_pid, SIGTERM);
				exit();
			case SIGHUP:
				self::load_plugins();
		}
	}

	/**
	 * Event trigger.
	 * @param mixed $event_name
	 * @param mixed $event_id
	 * @param mixed $event_arr
	 * @return mixed
	 */
	private static function event_trigger(string $event_name, Uuid $event_id, array $event_arr) {
		$mq = self::$message_queue;
		foreach (self::$listeners[$event_name] as $event_object) {
			// fork for each of the events so they can have their own process
			$pid = pcntl_fork();
			if ($pid == -1) {
				// failed
				die("Unable to fork");
			} elseif ($pid) {
				// wait for worker
				pcntl_wait($status, WNOHANG);
			} elseif ($pid === 0) {
				// worker process
				$mq->enqueue($event_id, "STARTED\n");
				// fs_logger::log("Triggering event $uuid");
				// Use anonymous function to kill before exit otherwise the ESL connection will close
				register_shutdown_function(function ($event_id, message_queue $q) {
					$q->enqueue($event_id, "COMPLETE\n");
					posix_kill(posix_getpid(), SIGKILL);
				}, $event_id, $mq);
				// create instance of plugin
				$obj = new $event_object($event_id, $event_arr);
				// call plugin
				$obj->event_triggered();
				exit();
			}
		}
	}

	/**
	 * Run supervisor.
	 * @return void
	 */
	private static function run_supervisor(): void {
		// variable short name
		$mq      = self::$message_queue;
		// get the system process id
		$sup_pid = posix_getpid();
		// running in supervisor process
		$mq->enqueue($sup_pid, "SUPERVISOR STARTED\n");
		// ensure running flag is set
		while (self::$running) {
			// iterate over all messages
			while (!$mq->empty()) {
				// get the id or uuid of the message group
				foreach ($mq->queue() as $id => $msgs) {
					// iterate over the messages in the group
					foreach ($msgs as $ndx => $msg) {
						// print the message to the console
						print ("$id: $msg");
					}
					// remove the message group that has been printed
					$mq->remove($id);
				}
			}

			if (isset($mq->cmd)) {
				// determine action for command
				switch ($mq->cmd) {
					case self::SUPERVISOR_EXIT:
						// display on cli
						print "SHUTTING DOWN...\n";
						// flip running switch to off
						self::$running = false;
						// send the user signal 1 to start shutdown
						posix_kill($sup_pid, SIGUSR1);
						// supervisor exit finished
						break;
					case self::SUPERVISOR_RELOAD_PLUGINS:
						// print immediately to the console
						print "RELOADING PLUGINS...\n";
						// send the signal to reload the plugins
						posix_kill($sup_pid, SIGHUP);
						break;  // reload plugins finished
				}
				// clear the command after processing it
				$mq->cmd = "";
			}
			// sleep to use less cpu resources
			sleep(1);
		}                       // while running
		// running is done so print done
		print "DONE.\n";
	}
}

