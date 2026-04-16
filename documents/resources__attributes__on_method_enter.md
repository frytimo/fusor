# resources/attributes/on_method_enter.php

## Summary
- Classes/Interfaces/Traits: on_method_enter
- Functions/Methods: 1

## Classes
- on_method_enter

## Functions and Methods
- __construct

## Purpose

Autocomplete-friendly convenience attribute for an `enter`-phase method hook.

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
