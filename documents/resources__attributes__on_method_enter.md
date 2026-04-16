# resources/attributes/on_method_enter.php

## Summary
- Classes/Interfaces/Traits: on_method_enter
- Functions/Methods: 1

## Classes
- on_method_enter

## Functions and Methods
- __construct

## Purpose

Runs your handler **before** the target function or method executes. The handler receives a `fusor_event` with the call arguments but no return value (since the original hasn't run yet). The original function always executes normally afterward — this hook cannot prevent it or change its return value.

Use this to log calls, validate arguments, emit metrics, or trigger side-effects before a function runs. Works on both static and instance methods via `uopz_set_hook`.

This is a convenience wrapper around `#[on_method(target: '...', event_name: 'enter')]` that removes the need to specify the phase.

## Example

```php
use Frytimo\Fusor\resources\attributes\on_method_enter;

class my_hooks {

    #[on_method_enter(target: 'my_service::calculate_total')]
    public static function trace_enter(array $context): void {
        syslog(LOG_INFO, 'Entering ' . $context['target']);
    }
}
```

## Source
- ../resources/attributes/on_method_enter.php
