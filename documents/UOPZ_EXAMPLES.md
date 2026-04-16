# UOPZ Examples for Fusor

This workspace now supports optional attribute-based auto-wiring when the uopz extension is available.

Current verified state in this repository:

- method and function target auto-wiring is enabled through the Fusor bootstrap
- constant override support is working
- exit wrapper behavior is working
- runtime function registration is working
- graceful syslog logging is active when uopz-related issues occur

## Supported hook names

- `enter`
- `exit`
- `before` → alias of `enter`
- `after` → alias of `exit`
- `around`
- `replace`

## Convenience attributes

For autocomplete and better static inspection, Fusor now also provides:

- `on_method_enter`
- `on_method_exit`
- `on_method_before`
- `on_method_after`
- `method_around`
- `method_replace`

## Notes on behavior

- `enter` is the native pre-execution hook path.
- `exit` is implemented through a safe wrapper so post-execution logic can inspect or replace the return value.
- `before` and `after` are aliases for `enter` and `exit`.
- `around` and `replace` are accepted names for advanced wrapper-style flows.
- The first implementation is intended for public static methods and global functions.

## Method hook example

```php
use Frytimo\Fusor\resources\attributes\on_method;
use Frytimo\Fusor\resources\attributes\on_method_enter;
use Frytimo\Fusor\resources\attributes\on_method_after;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_hooks {

    #[on_method(target: 'my_service::calculate_total', event_name: 'enter')]
    public static function trace_enter(fusor_event $event): void {
        syslog(LOG_INFO, 'Entering ' . $event->target);
    }

    #[on_method_enter(target: 'my_service::calculate_total')]
    public static function trace_enter_autocomplete(fusor_event $event): void {
        syslog(LOG_INFO, 'Entering via convenience attribute ' . $event->target());
    }

    #[on_method_after(target: 'my_service::format_value')]
    public static function trace_exit(fusor_event $event): string {
        return (string) $event->result . ' [hooked]';
    }
}
```

## Runtime function add/remove examples

```php
use Frytimo\Fusor\resources\attributes\runtime_function;

class my_runtime_functions {

    #[runtime_function(target: 'my_debug_helper', action: 'add')]
    public static function my_debug_helper(string $name = 'FusionPBX'): string {
        return 'Hello ' . $name;
    }

    #[runtime_function(target: 'my_old_helper', action: 'remove')]
    public static function unload_old_helper(): void {
    }
}
```

## Smoke test

Run the local smoke test to see the hook and runtime function behavior:

```bash
php /var/www/fusionpbx/tests/fusor_uopz_smoke.php
```

The demo classes live in [app/fusor_example/resources/classes/fusor_uopz_demo.php](app/fusor_example/resources/classes/fusor_uopz_demo.php).
If uopz is missing or broken, Fusor skips the auto-wiring and writes a syslog error instead of fatally breaking the request.

Useful related files:

- [app/fusor/resources/classes/fusor_uopz.php](app/fusor/resources/classes/fusor_uopz.php)
- [app/fusor/resources/attributes/on_method.php](app/fusor/resources/attributes/on_method.php)
- [app/fusor/resources/attributes/runtime_function.php](app/fusor/resources/attributes/runtime_function.php)
- [tests/fusor_uopz_smoke.php](tests/fusor_uopz_smoke.php)
