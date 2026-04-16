# Fusor Examples — API Reference & User Manual

This directory contains working PHP examples for every Fusor override method
detected by the `auto_loader`. Each file is a self-contained class that you
can drop into your own app's `resources/classes/` folder. Fusor will discover
it automatically — no registration code required.

## Quick start

1. Create a new app directory, e.g. `app/my_hooks/resources/classes/`.
2. Copy one or more example files into that directory.
3. Refresh the autoloader cache:
   ```bash
   /var/www/fusionpbx/app/fusor/resources/fusor --update-cache
   ```
4. Restart PHP-FPM to pick up changes:
   ```bash
   systemctl restart php8.4-fpm
   ```

## Directory layout

| File | Attribute(s) | Description |
|------|-------------|-------------|
| [01_on_event_listener.php](01_on_event_listener.php) | `#[on]` | Listen to named events (page render, FreeSWITCH events) |
| [02_http_get_hook.php](02_http_get_hook.php) | `#[http_get]` | Hook GET requests to any URL path |
| [03_http_post_hook.php](03_http_post_hook.php) | `#[http_post]` | Hook POST requests to any URL path |
| [04_on_method_enter.php](04_on_method_enter.php) | `#[on_method_enter]` | Run code before a method executes (uopz) |
| [05_on_method_exit.php](05_on_method_exit.php) | `#[on_method_exit]` | Run code after a method returns (uopz) |
| [06_on_method_before.php](06_on_method_before.php) | `#[on_method_before]` | Alias for enter — run code before a method |
| [07_on_method_after.php](07_on_method_after.php) | `#[on_method_after]` | Alias for exit — run code after a method |
| [08_method_around.php](08_method_around.php) | `#[method_around]` | Wrap a method call with before+after logic (uopz) |
| [09_method_replace.php](09_method_replace.php) | `#[method_replace]` | Completely replace a method's behavior (uopz) |
| [10_runtime_function.php](10_runtime_function.php) | `#[runtime_function]` | Add or remove global functions at runtime (uopz) |
| [12_login_hooks.php](12_login_hooks.php) | `#[http_get]`, `#[http_post]`, `#[on_method_exit]` | Complete login page customization |
| [13_logout_hooks.php](13_logout_hooks.php) | `#[http_get]` | Intercept logout and redirect |
| [14_page_render_hooks.php](14_page_render_hooks.php) | `#[on]` | Inject HTML before/after any page renders |
| [15_bridges_copy_hook.php](15_bridges_copy_hook.php) | `#[on_method_enter]`, `#[on_method_exit]` | Hook bridges copy/delete/toggle operations |
| [16_custom_sort_method.php](16_custom_sort_method.php) | `#[runtime_function]` | Provide a custom sort function at runtime |
| [17_multi_hook_class.php](17_multi_hook_class.php) | Multiple attributes | Single class with many hooks across FusionPBX |
| [18_freeswitch_events.php](18_freeswitch_events.php) | `#[on]` | Listen to FreeSWITCH event socket events |
| [19_wildcard_event_listener.php](19_wildcard_event_listener.php) | `#[on]` | Wildcard and glob patterns for event matching |
| [20_dashboard_widget_hooks.php](20_dashboard_widget_hooks.php) | `#[http_get]` | Modify dashboard page output |

## Requirements

- **PHP 8.2+** (PHP 8.4 recommended)
- **Fusor** installed at `app/fusor/`
- **uopz** extension required for: `on_method_enter`, `on_method_exit`, `on_method_before`, `on_method_after`, `method_around`, `method_replace`, `runtime_function`
- **No uopz needed for**: `on`, `http_get`, `http_post` (these use the event dispatcher only)

## How auto-discovery works

1. The `.env` file at `app/fusor/.env` defines scan paths.
2. On every request, `auto_loader` scans PHP files in `*/resources/classes/*.php`.
3. PHP 8 attributes (`#[...]`) on public static methods are parsed and indexed.
4. The bootstrap stages (100–140) register discovered listeners and hooks.
5. Your method is called automatically when the matching event fires.

## Attribute hierarchy

```
on                          ← base event listener (no uopz)
├── http                    ← abstract HTTP base
│   ├── http_get            ← GET request hook
│   └── http_post           ← POST request hook
├── on_method               ← uopz method hook base
│   ├── on_method_enter     ← pre-execution hook
│   ├── on_method_exit      ← post-execution hook
│   ├── on_method_before    ← alias for enter
│   ├── on_method_after     ← alias for exit
│   ├── method_around       ← wraps before + after
│   └── method_replace      ← completely replaces
└── runtime_function        ← add/remove runtime functions
```

## Method signature requirements

All hooked methods **must** be:
- `public` — the dispatcher needs to call them
- `static` — no instance is created by the dispatcher

Accepted method signatures:
```php
// Receive a fusor_event object (recommended)
public static function my_hook(fusor_event $event): void { }

// Receive raw context array (uopz hooks)
public static function my_hook(array $context): void { }

// No parameters (fire-and-forget)
public static function my_hook(): void { }

// Return a value (uopz exit/around/replace hooks)
public static function my_hook(fusor_event $event): mixed { }
```

## Event data available in hooks

### `#[on]` event listeners
```php
$event->name  // Event name, e.g. "before_render_bridges"
$event->data  // Associative array of event payload
$event->uuid  // Unique event ID
```

### `#[http_get]` / `#[http_post]` hooks
```php
$event->method     // "GET" or "POST"
$event->path       // Request path, e.g. "/app/bridges/bridges.php"
$event->query      // $_GET array (raw)
$event->query_safe // Filtered query parameters
$event->body       // $_POST array (raw)
$event->body_safe  // Filtered POST parameters
$event->html       // HTML output (by reference in 'after' stage)
$event->url        // http_request_url instance
```

### `#[on_method_*]` uopz hooks
```php
$event->phase      // "enter", "exit", "around", "replace"
$event->target     // "ClassName::methodName"
$event->arguments  // Arguments passed to the hooked method
$event->result     // Return value (exit/around/replace only)
```
