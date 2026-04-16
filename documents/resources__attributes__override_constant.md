# resources/attributes/override_constant.php

## Summary
- Classes/Interfaces/Traits: override_constant
- Functions/Methods: 1

## Classes
- override_constant

## Functions and Methods
- __construct

## Purpose

Redefines a global or class constant when the optional uopz extension is available.

## Example

```php
use Frytimo\Fusor\resources\attributes\override_constant;

class my_constant_overrides {

    #[override_constant(target: 'my_service::TIMEOUT', value: 45)]
    public static function timeout_override(): int {
        return 45;
    }
}
```

## Notes

- Works with global constants and class constants.
- Intended for controlled overrides and diagnostics.
- Fusor handles missing uopz support gracefully and logs the failure.

## Source
- ../resources/attributes/override_constant.php
