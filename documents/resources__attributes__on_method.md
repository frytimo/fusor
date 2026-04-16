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

Intercepts calls to any existing PHP function or public static method at runtime using the uopz extension. When the target function or method is called anywhere in the application, Fusor transparently runs your hook handler at the specified phase of execution. The original caller is unaware that a hook is active.

This allows plugin authors to observe, modify, or completely replace the behavior of any function or method in FusionPBX without editing the original source code. Hooks are registered automatically during bootstrap when the auto-loader discovers this attribute on a class method.

### Phases

| Phase | Behavior | Original runs? | Can change return value? |
|-------|----------|----------------|-------------------------|
| `enter` | Runs **before** the original executes. Receives the arguments but not the result. Uses `uopz_set_hook`. | Yes, immediately after | No |
| `exit` | Runs **after** the original executes. Receives both arguments and the return value. Uses `uopz_set_return` wrapper. | Yes, first | Yes — return a value from the handler to replace it |
| `before` | Alias of `enter` | Yes | No |
| `after` | Alias of `exit` | Yes | Yes |
| `around` | Wraps the original call. The original executes inside the wrapper and the handler receives the result. Uses `uopz_set_return` wrapper. | Yes, inside wrapper | Yes |
| `replace` | Same wrapper mechanism as `around`. The original executes first, then the handler can return a completely different value. Uses `uopz_set_return` wrapper. | Yes, inside wrapper | Yes |

### Requirements

- The **uopz** PHP extension must be loaded. If missing, hooks are silently skipped and a syslog warning is written.
- `exit`/`after`/`around`/`replace` phases only work on **public static methods** and **global functions** (instance method wrappers are not supported).
- `enter`/`before` works on any function or method (static or instance).

### Convenience attributes

These sub-classes lock in a specific phase so you don't need to pass `event_name`:

- `on_method_enter` — pre-execution hook
- `on_method_exit` — post-execution hook with return value access
- `on_method_before` — alias of `on_method_enter`
- `on_method_after` — alias of `on_method_exit`
- `method_around` — wraps the original call
- `method_replace` — wraps the original call (same as around)

## Example

```php
use Frytimo\Fusor\resources\attributes\on_method;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_runtime_hooks {

    #[on_method(target: 'my_service::calculate_total', event_name: 'enter')]
    public static function trace_enter(fusor_event $event): void {
        syslog(LOG_INFO, '[my_runtime_hooks] entering ' . $event->target);
    }

    #[on_method(target: 'my_service::format_value', event_name: 'exit')]
    public static function decorate_result(fusor_event $event): string {
        return (string) $event->result . ' [hooked]';
    }
}
```

## Notes

- `enter` runs before the original target executes.
- `exit` uses a wrapper so the original return value can be inspected or replaced.
- Hook handlers receive a `fusor_event` object. Use `$event->target` or `$event->target()` to inspect the hooked target.
- If uopz is missing or broken, Fusor skips the wiring and logs to syslog.

## Source
- ../resources/attributes/on_method.php
