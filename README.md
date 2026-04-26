# Fusor for FusionPBX

Fusor is an attribute-driven extension layer originally designed for FusionPBX but has grown to allow any just about any PHP project. It lets you register event listeners and HTTP lifecycle hooks without patching core files.

It supports four main extension patterns:

- Render lifecycle hooks for web pages (for example, `before_render_login`)
- HTTP lifecycle hooks via PHP attributes (`#[http_get]`, `#[http_post]`)
- Switch event listeners handled by the Fusor service (`#[on(event_name: 'switch.*')]`)
- Optional uopz-backed runtime auto-wiring for ~~constants~~, functions, methods, and runtime helper functions

**This must not be used in a production environment as it is considered a security risk because it has not been penetration tested.**

## Requirements

- PHP 8.2+
- Composer (dependencies for `fusor`)
- PHP opcache extension with both `opcache.preload` and `auto_prepend_file` enabled
- PHP uopz extension (optional, only required for runtime hook and override features)
- Restarting PHP-FPM

## Directory Overview

- `fusor/bootstrap.php`: loads Fusor and all app bootstrap files (`*/bootstrap/*.php`) in lexical order
- `fusor/resources/attributes/`: hook attributes (`on`, `http_get`, `http_post`, `on_method`, `on_method_enter`, `on_method_exit`, `on_method_before`, `on_method_after`, `method_around`, `method_replace`, ~~`override_constant`~~, `runtime_function`)
- `fusor/resources/classes/`: dispatcher, discovery, and optional uopz internals
- `fusor/resources/fusor`: Fusor CLI utility (cache refresh/version/help)
- `fusor/resources/service/fusor.php`: service entrypoint for switch event processing
- `fusor/env-example`: sample Fusor `.env` config

## Quick Start

### Git Clone Method

1. Clone the Fusor repository

```bash
cd /var/www/fusionpbx/app
git clone https://github.com/frytimo/fusor.git fusor
```

### Local app layout inside FusionPBX

2. Install Fusor dependencies:

```bash
cd /var/www/fusionpbx/app/fusor
composer install
```

3. Configure PHP-FPM to autoload Fusor:

```bash
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"); 
PHP_INI=/etc/php/$PHP_VERSION/fpm/php.ini

# Enable opcache.preload
sed -i 's#;opcache.preload=#opcache.preload=/var/www/fusionpbx/app/fusor/bootstrap.php#g' $PHP_INI

# Enable auto_prepend_file
sed -i 's#;auto_prepend_file = /var/www/fusionpbx/app/fusor/bootstrap.php#auto_prepend_file = /var/www/fusionpbx/app/fusor/bootstrap.php#g' $PHP_INI
sed -i 's#^auto_prepend_file = $#auto_prepend_file = /var/www/fusionpbx/app/fusor/bootstrap.php#g' $PHP_INI

sudo systemctl restart php$PHP_VERSION-fpm
```

4. Configure Fusor:

```bash
cd /var/www/fusionpbx/app/fusor
cp env-example .env
```

### Root vendor installation via Composer

Fusor can also run when installed into a root `vendor/` folder. The bootstrap now detects this layout automatically.

Example consumer setup:

```bash
composer require frytimo/fusor
```

Then load Fusor from your project bootstrap:

```php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/frytimo/fusor/bootstrap.php';
```

The packaged CLI is also exposed through Composer bin support:

```bash
vendor/bin/fusor --help
```

## Namespace Policy

The canonical public namespace is:

```php
use Frytimo\Fusor\resources\classes\fusor_event;
```
The mixed case is needed for the Composer author and project name and then the FusionPBX strict snake_case requirement.

## .ENV File

The auto-loader runs with caching provided by PHP opcache (bytecode) and APCu (class map).

**Both PHP settings are required for Fusor to function:**
- **`opcache.preload`**: Pre-compiles bootstrap.php and auto_loader.php at FPM startup, improving bytecode availability performance
- **`auto_prepend_file`**: Executes bootstrap.php at the start of every request to register the SPL autoload handler and initialize Fusor

Without `auto_prepend_file`, classes cannot be found because the autoload handler is never registered on incoming requests.

### Configurable scan paths:

```ini
scan_path.0 = '/resources/interfaces/*.php',
scan_path.1 = '/resources/traits/*.php',
scan_path.2 = '/resources/classes/*.php',
scan_path.3 = '/resources/classes/*/*.php',
scan_path.4 = '/*/*/resources/interfaces/*.php',
scan_path.5 = '/*/*/resources/traits/*.php',
scan_path.6 = '/*/*/resources/classes/*.php',
scan_path.7 = '/*/*/resources/classes/*/*.php',
scan_path.8 = '/core/authentication/resources/classes/plugins/*.php',
```

These are the standard locations the FusionPBX Auto Loader scans. Changing or removing these could have unintended consequences. Instead, you can add locations your application might require. For example:

```ini
scan_path.9 = '/app/my_app/resources/classes/sub-classes/*.class.php',
```

## Logging Configuration

Fusor provides integrated logging through syslog and optional file-based logging. Logs are automatically written to the system syslog (facility `LOG_USER`, ident `FusionPBX Fusor`).

### Enable File-Based Logging

To additionally log Fusor events to a file, configure the log file path in the `.env` file:

```ini
log_file=/var/log/fusionpbx/fusor.log
```

Alternatively, set via process environment (takes priority over `.env`):

```bash
export FUSOR_LOG_FILE=/var/log/fusionpbx/fusor.log
```

The logger will create the file if it does not exist (permissions permitting). Each log entry includes a timestamp, priority level, process ID, and message.

### Log File Permissions

Ensure the log directory is writable by the PHP-FPM user (typically `www-data`):

```bash
mkdir -p /var/log/fusionpbx
chown -R www-data:www-data /var/log/fusionpbx
chmod 755 /var/log/fusionpbx
```

### Viewing Logs

View syslog entries:

```bash
journalctl -u php8.4-fpm -g "FusionPBX Fusor" -n 100
# or
grep "FusionPBX Fusor" /var/log/syslog
```

View file-based logs:

```bash
tail -f /var/log/fusionpbx/fusor.log
```

### Disable File Logging

To use only syslog and disable file logging, either:

1. Omit the `log_file` setting in `.env`
2. Set it to `/dev/null`

### Auto Loader Debug Logging

Configure the logging level from the auto loader with `debug_level` in `.env`:

```ini
; Auto Loader Debug Logging level: debug | info | notice | warning | error | false
; true is equivalent to debug (most verbose). false disables auto_loader log output.
debug_level=false
```

**Supported levels** (in order of verbosity):
- `debug` or `true`: Show all messages (debug, info, notice, warning, error)
- `info`: Show info, notice, warning, error messages
- `notice`: Show notice, warning, error messages
- `warning`: Show warning and error messages only
- `error`: Show errors only
- `false`: Disable auto_loader logging

Keep this `false` in production. When enabled, messages are written via syslog and optional file logging.


The source is prepared with PHPDoc comments so API docs can be regenerated quickly.

1. Install phpDocumentor (or use your existing tooling).
2. Run it from `app/fusor` with vendor/tests excluded.

Example:

```bash
cd /var/www/fusionpbx/app/fusor
phpdoc -d . -t documents/api --ignore "vendor/*,tests/*,documents/*"
```

The `documents/` folder contains file/class/function reference pages for Fusor source files.

## Publishing Checklist

Before publishing a release:

1. Run Composer validation from the Fusor package directory.
2. Run the Fusor test scripts and confirm they all succeed.
3. Verify a temporary root-level Composer install still boots correctly from `vendor/frytimo/fusor`.
4. Review the canonical namespace examples in this README and the documents folder.
5. Tag the release only after both the local app layout and the vendor layout have been verified.

## How Fusor Works

### 1) Attribute Discovery

Fusor uses the enhanced FusionPBX `auto_loader` metadata to discover attributes and register matching static public methods.

### 2) Dispatch

- `#[on(...)]` methods are registered with priority support (higher priority runs first).
- Wildcards are supported using `fnmatch` semantics (for example, `switch.*`).
- HTTP lifecycle hooks are dispatched once per request for `GET` and `POST`.
- When uopz is installed, Fusor can also auto-wire function and method entry or exit hooks, constant overrides, and runtime helper functions.

### 3) Request Lifecycle Hooks

For a script named `login.php`, Fusor emits:

- `before_render_login`
- `after_render_login`

Likewise, `logout.php` maps to:

- `before_render_logout`
- `after_render_logout`

The event payload contains `html` by reference for render hooks.

## Attribute Reference

### `#[on(event_name: string, priority: int = 0)]`

Use for generic events, including render hooks and switch-relayed events.

```php
use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_listener {

    #[on(event_name: 'before_render_login', priority: 50)]
    public static function before_login(fusor_event $event): void {
        // $event->html is available for render hooks
    }
}
```

### `#[http_get('/path', stage: 'before|after', priority: int)]`, `#[http_post('/path', stage: 'before|after', priority: int)]`

Use for path-qualified HTTP lifecycle events.

Examples of event names generated by attributes:

- `before_http_get:/resources/login.php`
- `after_http_post:/resources/login.php`

You can use wildcard patterns in the path:

- `*` matches any characters (including `/`) in the remaining path
- `/*` can be configured to also match the base directory itself
- `/**/` matches zero or more directory segments (useful for filename matching across any depth)

Examples:

- `#[http_get('/app/*', stage: 'before')]`
- `#[http_get('/core/dashboard/*', stage: 'before')]`
- `#[http_get('/**/index.php', stage: 'before')]`

Path matching notes:

- Request matching uses the URL path from `REQUEST_URI` (query string is ignored)
- Paths are normalized to remove duplicate slashes and trailing slash (except `/`)
- `/core/dashboard/?key=true` normalizes to `/core/dashboard`
- `/core/dashboard/index.php?key=1t4e39i` normalizes to `/core/dashboard/index.php`

Directory wildcard toggle:

- Setting: `match_directory_on_wildcard=true|false`
- Default: `true`
- When `true`, `/core/dashboard/*` also matches `/core/dashboard`
- When `false`, `/core/dashboard/*` requires at least one additional segment

HTTP lifecycle event payload includes:

- `method`
- `path`
- `params`
- `query` (legacy raw `$_GET` values for compatibility)
- `query_safe` (filtered query values)
- `body` (legacy raw `$_POST` values for compatibility)
- `body_safe` (filtered POST and input-body values)
- `url` (shared League URI-backed adapter with safe and unsafe access helpers)

When `stage: 'after'` listeners are used, payload also includes `html` by reference so response output can be inspected or changed.

### `#[on_method(target: string, event_name: string = 'enter', priority: int = 0)]`

Use this optional attribute when the PHP uopz extension is available and you want Fusor to auto-wire a hook onto an existing function or static method.

Supported phase names:

- `enter`
- `exit`
- `before` → alias of `enter`
- `after` → alias of `exit`
- `around`
- `replace`

Autocomplete-friendly convenience attributes are also available:

- `on_method_enter`
- `on_method_exit`
- `on_method_before`
- `on_method_after`
- `method_around`
- `method_replace`

Notes:

- `enter` uses the uopz hook path and runs before the target executes.
- `exit` currently uses a wrapper approach and runs after the original target returns.
- The first implementation is intended for global functions and public static methods.
- If uopz is not loaded or not fully available, the request continues normally and Fusor writes a syslog error for the developer.

Examples:

```php
use Frytimo\Fusor\resources\attributes\on_method;
use Frytimo\Fusor\resources\attributes\on_method_enter;
use Frytimo\Fusor\resources\attributes\on_method_after;

class my_runtime_hooks {

    #[on_method(target: 'my_service::calculate_total', event_name: 'enter')]
    public static function trace_enter(array $context): void {
        syslog(LOG_INFO, '[my_runtime_hooks] entering ' . $context['target']);
    }

    #[on_method_enter(target: 'my_service::calculate_total')]
    public static function trace_enter_autocomplete(array $context): void {
        syslog(LOG_INFO, '[my_runtime_hooks] enter alias ' . $context['target']);
    }

    #[on_method_after(target: 'my_service::format_value')]
    public static function decorate_result(array $context): string {
        return (string) $context['result'] . ' [hooked]';
    }
}
```

### `#[override_constant(target: string, value: mixed = null, priority: int = 0)]`

Use this optional attribute to redefine a global or class constant when uopz is available.

Example:

```php
use Frytimo\Fusor\resources\attributes\override_constant;

class my_constant_overrides {

    #[override_constant(target: 'my_service::TIMEOUT', value: 45)]
    public static function timeout_override(): int {
        return 45;
    }
}
```

### `#[runtime_function(target: string, action: string = 'add', priority: int = 0)]`

Use this optional attribute to add or remove a runtime helper function using uopz.

Supported action names:

- `add`
- `load` → alias of `add`
- `remove`
- `unload` → alias of `remove`
- `delete` → alias of `remove`

Example:

```php
use Frytimo\Fusor\resources\attributes\runtime_function;

class my_runtime_functions {

    #[runtime_function(target: 'my_debug_helper', action: 'add')]
    public static function my_debug_helper(string $name = 'FusionPBX'): string {
        return 'Hello ' . $name;
    }
}
```

### Shared URL Adapter

HTTP events now expose `$event->url`, a shared request URL helper powered by League URI. This gives hooks a consistent API for reading normalized paths plus safe and unsafe values without having to parse `REQUEST_URI`, `$_GET`, or `$_POST` manually.

Common examples:

```php
$path = $event->url->get_path();
$status = $event->url->get_query_param('status');              // filtered value
$raw_status = $event->url->get_query_param('status', null, true); // unsafe raw value
$username = $event->url->post('username');
$note_raw = $event->url->post('note', null, true);
```

## Example 1: Create a New HTTP Lifecycle Hook Example

This example shows how to add a new `POST` after hook.

1. Create bootstrap loader:

`app/my_fusor_demo/resources/bootstrap/10-hooks.php`

```php
<?php

require_once dirname(__DIR__) . '/classes/my_fusor_demo_hooks.php';
```

2. Create hook class:

`app/my_fusor_demo/resources/classes/my_fusor_demo_hooks.php`

```php
<?php

use Frytimo\Fusor\resources\attributes\http_post;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_fusor_demo_hooks {

    #[http_post('/api/my-fusor-demo/orders/*', stage: 'after')]
    public static function create_order(fusor_event $event): void {
        $path = $event->url?->get_path() ?? (string) ($event->path ?? '');
        $payload = is_array($event->body_safe) ? $event->body_safe : [];
        $status = $event->url?->get_query_param('status');

        error_log('[my_fusor_demo] POST path: ' . $path . ' status=' . $status . ' body=' . json_encode($payload));
    }
}
```

3. Trigger request:

```bash
curl -X POST \
  "https://your-pbx.example/api/my-fusor-demo/orders/42" \
  -d "status=created"
```

## Example 2: Create Login/Logout Hooks

This example demonstrates hooks for FusionPBX login/logout flow.

1. Create class:

`app/my_fusor_auth_hooks/resources/classes/my_fusor_auth_hooks.php`

```php
<?php

use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_fusor_auth_hooks {

    #[on(event_name: 'before_render_login', priority: 100)]
    public static function on_before_login(fusor_event $event): void {
        error_log('[fusor auth] before_render_login');
    }

    #[on(event_name: 'after_render_login', priority: 100)]
    public static function on_after_login(fusor_event $event): void {
        $html = (string) ($event->html ?? '');
        error_log('[fusor auth] after_render_login html_length=' . strlen($html));
    }

    #[on(event_name: 'before_render_logout', priority: 100)]
    public static function on_before_logout(fusor_event $event): void {
        error_log('[fusor auth] before_render_logout');
    }

    #[on(event_name: 'after_render_logout', priority: 100)]
    public static function on_after_logout(fusor_event $event): void {
        error_log('[fusor auth] after_render_logout');
    }
}
```

2. Ensure autoload metadata sees the class. Either:

- Place it under one of the scanned class paths, or
- Require it from a bootstrap file under `resources/bootstrap/`

3. Test by visiting:

- `/login.php`
- `/logout.php`

4. Verify messages in your PHP error log.

Notes:

- `stage: 'before'` runs early in the request and can set headers/redirect.
- `stage: 'after'` runs in shutdown and is best for logging and output post-processing.
- Keep heavy logic out of request hooks.

## Fusor Service (Switch Event Relay)

Fusor CLI utility:

```bash
/var/www/fusionpbx/app/fusor/resources/fusor --help
```

Install globally (optional, recommended via symlink):

```bash
ln -sf /var/www/fusionpbx/app/fusor/resources/fusor /usr/local/bin/fusor
chmod +x /usr/local/bin/fusor
```

Copy-based install (alternative):

```bash
cp /var/www/fusionpbx/app/fusor/resources/fusor /usr/local/bin/fusor
chmod +x /usr/local/bin/fusor
```

After install, run:

```bash
fusor --help
```

Useful CLI utility options:

- `-u` or `--update-cache` — full rebuild of all cache maps (classes, interfaces, inheritance, attributes)
- `-r [target]` or `--rebuild-cache[=target]` — rebuild a specific cache map:
  - `all` (default) — same as `--update-cache`; rebuilds everything
  - `classes` — rebuild class/interface/inheritance maps only (attribute metadata untouched)
  - `interfaces` — alias for `classes`
  - `inheritance` — alias for `classes`
  - `attributes` — rebuild all attribute metadata only (class maps untouched)
  - `methods` — rebuild method attribute sub-map only
- `-v` or `--version`
- `-h` or `--help`

When no option is provided, the utility checks whether the cache has expired (see `cache_expire_time` below)
and rebuilds automatically if needed, otherwise it performs a full refresh.

### Automatic cache expiry

Set `cache_expire_time` in `app/fusor/.env` to enable time-based invalidation:

```ini
; Expire the cache after this many seconds. 0 = never expire automatically.
cache_expire_time=3600
```

When `cache_expire_time > 0`:

- **File cache** — the `written_at` timestamp stored in the cache file is compared on each load. An
  expired cache is discarded and rebuilt on the next request.
- **APCu cache** — the value is passed as the native TTL to `apcu_store`, so APCu expires the keys
  automatically without any additional check.
- **CLI auto-expiry** — every time the `fusor` binary runs (for any reason), if the file cache is
  older than `cache_expire_time` it is automatically rebuilt before any other operation.

### File cache location

By default, the auto-loader writes its file cache (`autoloader_cache.php`) to `sys_get_temp_dir()/fusionpbx_cache`.

To use a custom location, set `auto_loader_cache_path` in `app/fusor/.env`:

```ini
auto_loader_cache_path=/var/cache/fusionpbx
```

The directory must be writable by the PHP process user (typically `www-data`). Create it with the correct permissions before first use:

```bash
mkdir -p /var/cache/fusionpbx
chown www-data:www-data /var/cache/fusionpbx
chmod 750 /var/cache/fusionpbx
```

If the cache write fails you will see a warning in your log:

```
[WARNING] [auto_loader] Failed to write temporary autoloader file cache to /path/to/cache/autoloader_cache.php.tmp.
Check that the directory is writable by the PHP process user.
```

Use `auto_loader_cache_path` to redirect the cache to a location the web server user can write to.


If your FusionPBX root is not `/var/www/fusionpbx`, set `FUSOR_DIR` before running the global command:

```bash
export FUSOR_DIR=/path/to/fusionpbx/app/fusor
fusor --help
```

Run manually:

```bash
php /var/www/fusionpbx/app/fusor/resources/service/fusor.php
```

Useful options:

- `--switch-address=<ip>`
- `--switch-port=<port>`
- `--no-pcntl`
- `--dump-maps`
- `-m` or `--update-maps` (reload listener maps)

Systemd unit template exists at:

- `app/fusor/resources/service/debian-fusor.service`

## Uopz Runtime Notes

- uopz support is optional and fail-open by design.
- If the attribute is present but the extension is missing, disabled, or incomplete, Fusor skips the runtime wiring and writes an error to syslog.
- This behavior is compatible with opcache and preload, and the bootstrap guards avoid request fatals when request variables are not yet populated.
- Additional examples are documented in [app/fusor/documents/UOPZ_EXAMPLES.md](app/fusor/documents/UOPZ_EXAMPLES.md).
- For local validation, run:

```bash
php /var/www/fusionpbx/tests/fusor_uopz_smoke.php
```

The current verified smoke output shows:

- constant override working
- exit hook decoration working
- runtime function registration working

## Troubleshooting

### Hooks not firing

- Confirm PHP version is 8.2+.
- Confirm Fusor bootstrap is loaded.
- Ensure class files are in scan paths or required via bootstrap.
- Disable cache in `app/fusor/.env` while iterating.
- Restart php-fpm if opcache/preload keeps old class metadata.

### HTTP lifecycle hooks not firing in CLI tests

- Set in `app/fusor/.env`:

```ini
allow_cli=true
```

Keep this `false` in production unless you intentionally need CLI route dispatch.

### Service starts but no switch callbacks run

- Verify listener map with `--dump-maps`.
- Verify event names match exactly (or use wildcard such as `switch.*`).
- Use `--update-maps` after adding new listeners.

## Practical Conventions

- Keep listener methods `public static`.
- Keep hook logic idempotent and fast.
- Prefer logging/dispatching work to background jobs for heavier tasks.
- Use explicit event names and priorities.

## Version

Current Fusor service version constant: `1.0.0`
