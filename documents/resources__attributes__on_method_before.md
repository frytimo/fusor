# resources/attributes/on_method_before.php

## Summary
- Classes/Interfaces/Traits: on_method_before
- Functions/Methods: 1

## Classes
- on_method_before

## Functions and Methods
- __construct

## Purpose

Autocomplete-friendly alias for the `enter` phase.

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
