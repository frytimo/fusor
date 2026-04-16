# resources/attributes/method_around.php

## Summary
- Classes/Interfaces/Traits: method_around
- Functions/Methods: 1

## Classes
- method_around

## Functions and Methods
- __construct

## Purpose

Wraps the target function or method so your handler runs **around** the original call. When the target is called, Fusor's wrapper first executes the original function, then passes both the arguments and the return value to your handler via `fusor_event`. If the handler returns a non-null value, it **replaces** the original return value seen by the caller.

This gives you full control: you can inspect the original result and decide whether to keep it, modify it, or return something entirely different. Only works on **public static methods** and **global functions** (uses `uopz_set_return` internally).

This is a convenience wrapper around `#[on_method(target: '...', event_name: 'around')]` that removes the need to specify the phase.

## Example

```php
use Frytimo\Fusor\resources\attributes\method_around;

class my_hooks {

    #[method_around(target: 'my_service::calculate_total')]
    public static function wrap_call(array $context): mixed {
        return $context['result'] ?? null;
    }
}
```

## Source
- ../resources/attributes/method_around.php
