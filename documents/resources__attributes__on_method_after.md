# resources/attributes/on_method_after.php

## Summary
- Classes/Interfaces/Traits: on_method_after
- Functions/Methods: 1

## Classes
- on_method_after

## Functions and Methods
- __construct

## Purpose

Autocomplete-friendly alias for the `exit` phase.

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
