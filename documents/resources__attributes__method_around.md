# resources/attributes/method_around.php

## Summary
- Classes/Interfaces/Traits: method_around
- Functions/Methods: 1

## Classes
- method_around

## Functions and Methods
- __construct

## Purpose

Autocomplete-friendly convenience attribute for an `around`-style method hook.

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
