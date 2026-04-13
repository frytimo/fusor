# Fusor for FusionPBX

Fusor is an attribute-driven extension layer for FusionPBX. It lets you register event listeners and HTTP route hooks without patching core files.

It supports three main extension patterns:

- Render lifecycle hooks for web pages (for example, `before_render_login`)
- HTTP route hooks via PHP attributes (`#[get]`, `#[post]`)
- Switch event listeners handled by the Fusor service (`#[on(event_name: 'switch.*')]`)

## Requirements

- FusionPBX 5.6+ (master branch after Apr. 13, 2026)
- PHP 8.2+
- Composer (dependencies for `app/fusor`)
- PHP opcache extension

## Directory Overview

- `app/fusor/bootstrap.php`: loads Fusor and all app bootstrap files (`app/*/*/resources/bootstrap/*.php`) in lexical order
- `app/fusor/resources/attributes/`: hook attributes (`on`, `get`, `post`, `route`)
- `app/fusor/resources/classes/`: dispatcher and discovery internals
- `app/fusor/resources/fusor`: Fusor CLI utility (cache refresh/version/help)
- `app/fusor/resources/service/fusor.php`: service entrypoint for switch event processing
- `app/fusor/env-example`: sample Fusor `.env` config

## Quick Start

1. Install Fusor dependencies:

```bash
cd /var/www/fusionpbx/app/fusor
composer install
```

2. Configure PHP-FPM to autoload Fusor:

```
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"); sed -i "s#;opcache.preload=#opcache.preload=/var/www/fusionpbx/app/fusor/bootstrap.php#g" /etc/php/$PHP_VERSION/fpm/php.ini; systemctl restart php$PHP_VERSION-fpm
```

3. Configure Fusor:

```bash
cd /var/www/fusionpbx/app/fusor
cp env-example .env
```

## .ENV File

For development, keep cache disabled in `app/fusor/.env`:

```ini
[auto_loader]
cache=false
```

Configurable scan paths:

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

## Regenerate Documentation Quickly

The source is prepared with PHPDoc comments so API docs can be regenerated quickly.

1. Install phpDocumentor (or use your existing tooling).
2. Run it from `app/fusor` with vendor/tests excluded.

Example:

```bash
cd /var/www/fusionpbx/app/fusor
phpdoc -d . -t documents/api --ignore "vendor/*,tests/*,documents/*"
```

The `documents/` folder contains file/class/function reference pages for Fusor source files.

## How Fusor Works

### 1) Attribute Discovery

Fusor uses the enhanced FusionPBX `auto_loader` metadata to discover attributes and register matching static public methods.

### 2) Dispatch

- `#[on(...)]` methods are registered with priority support (higher priority runs first).
- Wildcards are supported using `fnmatch` semantics (for example, `switch.*`).
- Route hooks are dispatched once per request for `GET` and `POST`.

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
use frytimo\fusor\resources\attributes\on;
use frytimo\fusor\resources\classes\fusor_event;

class my_listener {

    #[on(event_name: 'before_render_login', priority: 50)]
    public static function before_login(fusor_event $event): void {
        // $event->html is available for render hooks
    }
}
```

### `#[get('/path')]`, `#[post('/path')]`

Use for HTTP request matching.

Supported route patterns:

- Exact path: `/api/item`
- Wildcard: `/api/*`
- Named params: `/api/items/{id}`

Route event payload includes:

- `method`
- `route`
- `path`
- `params`
- `query`
- `body`

## Example 1: Create a New Route Hook Example

This example shows how to add a new `POST` hook app.

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

use frytimo\fusor\resources\attributes\post;
use frytimo\fusor\resources\classes\fusor_event;

class my_fusor_demo_hooks {

    #[post('/api/my-fusor-demo/orders/{order_id}')]
    public static function create_order(fusor_event $event): void {
        $order_id = (string) ($event->params['order_id'] ?? '');
        $payload = is_array($event->body) ? $event->body : [];

        error_log('[my_fusor_demo] POST order: ' . $order_id . ' body=' . json_encode($payload));
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

use frytimo\fusor\resources\attributes\on;
use frytimo\fusor\resources\classes\fusor_event;

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

- `login.php` and `logout.php` in FusionPBX primarily redirect. These hooks are best for audit/logging and lightweight side effects.
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

- `-u` or `--update-cache` (refresh auto-loader cache)
- `-v` or `--version`
- `-h` or `--help`

When no option is provided, the utility refreshes cache by default.

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

## Troubleshooting

### Hooks not firing

- Confirm PHP version is 8.2+.
- Confirm Fusor bootstrap is loaded.
- Ensure class files are in scan paths or required via bootstrap.
- Disable cache in `app/fusor/.env` while iterating.
- Restart php-fpm if opcache/preload keeps old class metadata.

### Route hooks not firing in CLI tests

- Set in `app/fusor/.env`:

```ini
[http_route_hooks]
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
