# resources/attributes/on_method_exit.php

## Summary
- Classes/Interfaces/Traits: on_method_exit
- Functions/Methods: 1

## Classes
- on_method_exit

## Functions and Methods
- __construct

## Purpose

Runs your handler **after** the target function or method has executed. The handler receives a `fusor_event` containing both the original call arguments and the return value (`$event->result`). If the handler returns a non-null value, that value **replaces** the original return value seen by the caller.

Use this to inspect results, transform output, log return values, or conditionally override what a function returns. Requires `uopz_set_return` internally, so it only works on **public static methods** and **global functions** (not instance methods).

This is a convenience wrapper around `#[on_method(target: '...', event_name: 'exit')]` that removes the need to specify the phase.

## Example

```php
use Frytimo\Fusor\resources\attributes\on_method_exit;

class my_hooks {

    #[on_method_exit(target: 'my_service::format_value')]
    public static function trace_exit(array $context): string {
        return (string) $context['result'] . ' [hooked]';
    }
}
```

## Source
- ../resources/attributes/on_method_exit.php
