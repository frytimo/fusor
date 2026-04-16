# resources/attributes/on_method.php

## Summary
- Classes/Interfaces/Traits: on_method
- Functions/Methods: 2

## Classes
- on_method

## Functions and Methods
- __construct
- normalize_phase

## Purpose

Provides optional uopz-backed auto-wiring for an existing function or public static method.

Supported names:

- `enter`
- `exit`
- `before` (alias of `enter`)
- `after` (alias of `exit`)
- `around`
- `replace`

## Example

```php
use Frytimo\Fusor\resources\attributes\on_method;

class my_runtime_hooks {

    #[on_method(target: 'my_service::calculate_total', event_name: 'enter')]
    public static function trace_enter(array $context): void {
        syslog(LOG_INFO, '[my_runtime_hooks] entering ' . $context['target']);
    }

    #[on_method(target: 'my_service::format_value', event_name: 'exit')]
    public static function decorate_result(array $context): string {
        return (string) $context['result'] . ' [hooked]';
    }
}
```

## Notes

- `enter` runs before the original target executes.
- `exit` uses a wrapper so the original return value can be inspected or replaced.
- If uopz is missing or broken, Fusor skips the wiring and logs to syslog.

## Source
- ../resources/attributes/on_method.php
