# resources/attributes/on_method_exit.php

## Summary
- Classes/Interfaces/Traits: on_method_exit
- Functions/Methods: 1

## Classes
- on_method_exit

## Functions and Methods
- __construct

## Purpose

Autocomplete-friendly convenience attribute for an `exit`-phase method hook.

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
