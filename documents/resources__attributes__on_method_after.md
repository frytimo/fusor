# resources/attributes/on_method_after.php

## Summary
- Classes/Interfaces/Traits: on_method_after
- Functions/Methods: 1

## Classes
- on_method_after

## Functions and Methods
- __construct

## Purpose

Runs your handler **after** the target function or method has executed. Functionally identical to `on_method_exit` — this is an alias that uses the word "after" for readability when that reads more naturally in your code.

The handler receives a `fusor_event` containing both the original call arguments and the return value (`$event->result`). If the handler returns a non-null value, it **replaces** the original return value. Only works on **public static methods** and **global functions** (uses `uopz_set_return` internally).

This is a convenience wrapper around `#[on_method(target: '...', event_name: 'after')]` which normalizes to the `exit` phase.

## Example

```php
use Frytimo\Fusor\resources\attributes\on_method_after;

class my_hooks {

    #[on_method_after(target: 'my_service::format_value')]
    public static function trace_after(array $context): string {
        return (string) $context['result'] . ' [after]';
    }
}
```

## Source
- ../resources/attributes/on_method_after.php
