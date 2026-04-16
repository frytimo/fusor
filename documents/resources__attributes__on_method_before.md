# resources/attributes/on_method_before.php

## Summary
- Classes/Interfaces/Traits: on_method_before
- Functions/Methods: 1

## Classes
- on_method_before

## Functions and Methods
- __construct

## Purpose

Runs your handler **before** the target function or method executes. Functionally identical to `on_method_enter` — this is an alias that uses the word "before" for readability when that reads more naturally in your code.

The handler receives a `fusor_event` with the call arguments but no return value. The original function always executes normally afterward. Works on both static and instance methods via `uopz_set_hook`.

This is a convenience wrapper around `#[on_method(target: '...', event_name: 'before')]` which normalizes to the `enter` phase.

## Example

```php
use Frytimo\Fusor\resources\attributes\on_method_before;

class my_hooks {

    #[on_method_before(target: 'my_service::calculate_total')]
    public static function trace_before(array $context): void {
        syslog(LOG_INFO, 'Before ' . $context['target']);
    }
}
```

## Source
- ../resources/attributes/on_method_before.php
