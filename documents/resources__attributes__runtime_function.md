# resources/attributes/runtime_function.php

## Summary
- Classes/Interfaces/Traits: runtime_function
- Functions/Methods: 2

## Classes
- runtime_function

## Functions and Methods
- __construct
- normalize_action

## Purpose

Adds or removes a helper function or method at runtime when uopz is available.

Supported action names:

- `add`
- `load` (alias of `add`)
- `remove`
- `unload` (alias of `remove`)
- `delete` (alias of `remove`)

## Example

```php
use Frytimo\Fusor\resources\attributes\runtime_function;

class my_runtime_functions {

    #[runtime_function(target: 'my_debug_helper', action: 'add')]
    public static function my_debug_helper(string $name = 'FusionPBX'): string {
        return 'Hello ' . $name;
    }
}
```

## Notes

- Useful for diagnostics and temporary helper registration.
- The first verified implementation supports runtime helper registration in this workspace.

## Source
- ../resources/attributes/runtime_function.php
