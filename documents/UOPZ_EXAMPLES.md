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

## Notes on behavior

- `enter` is the native pre-execution hook path.
- `exit` is implemented through a safe wrapper so post-execution logic can inspect or replace the return value.
- `before` and `after` are aliases for `enter` and `exit`.
- `around` and `replace` are accepted names for advanced wrapper-style flows.
- The first implementation is intended for public static methods and global functions.

## Method hook example

```php
use Frytimo\Fusor\resources\attributes\on_method;

class my_hooks {

    #[on_method(target: 'my_service::calculate_total', event_name: 'enter')]
    public static function trace_enter(array $context): void {
        syslog(LOG_INFO, 'Entering ' . $context['target']);
    }

    #[on_method(target: 'my_service::format_value', event_name: 'exit')]
    public static function trace_exit(array $context): string {
        return (string) $context['result'] . ' [hooked]';
    }
}
```

## Constant override example

```php
use Frytimo\Fusor\resources\attributes\override_constant;

class my_overrides {

    #[override_constant(target: 'my_service::TIMEOUT', value: 45)]
    public static function override_timeout(): int {
        return 45;
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

Run the local smoke test to see the hook, constant override, and runtime function behavior:

```bash
php /var/www/fusionpbx/tests/fusor_uopz_smoke.php
```

The demo classes live in [app/fusor_example/resources/classes/fusor_uopz_demo.php](app/fusor_example/resources/classes/fusor_uopz_demo.php).
If uopz is missing or broken, Fusor skips the auto-wiring and writes a syslog error instead of fatally breaking the request.

Useful related files:

- [app/fusor/resources/classes/fusor_uopz.php](app/fusor/resources/classes/fusor_uopz.php)
- [app/fusor/resources/attributes/on_method.php](app/fusor/resources/attributes/on_method.php)
- [app/fusor/resources/attributes/override_constant.php](app/fusor/resources/attributes/override_constant.php)
- [app/fusor/resources/attributes/runtime_function.php](app/fusor/resources/attributes/runtime_function.php)
- [tests/fusor_uopz_smoke.php](tests/fusor_uopz_smoke.php)
