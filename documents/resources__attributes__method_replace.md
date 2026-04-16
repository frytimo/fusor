# resources/attributes/method_replace.php

## Summary
- Classes/Interfaces/Traits: method_replace
- Functions/Methods: 1

## Classes
- method_replace

## Functions and Methods
- __construct

## Purpose

Wraps the target function or method so your handler can **replace** the return value. Internally uses the same wrapper mechanism as `method_around`: the original function executes first, then your handler receives both the arguments and the original return value via `fusor_event`. If the handler returns a non-null value, it **replaces** the original return value seen by the caller.

Use `replace` when the intent of your hook is specifically to substitute a different return value, making the code's purpose self-documenting. Only works on **public static methods** and **global functions** (uses `uopz_set_return` internally).

This is a convenience wrapper around `#[on_method(target: '...', event_name: 'replace')]` that removes the need to specify the phase.

## Example

```php
use Frytimo\Fusor\resources\attributes\method_replace;

class my_hooks {

    #[method_replace(target: 'my_service::format_value')]
    public static function replace_result(array $context): string {
        return 'replacement result';
    }
}
```

## Source
- ../resources/attributes/method_replace.php
