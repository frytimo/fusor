# resources/attributes/method_replace.php

## Summary
- Classes/Interfaces/Traits: method_replace
- Functions/Methods: 1

## Classes
- method_replace

## Functions and Methods
- __construct

## Purpose

Autocomplete-friendly convenience attribute for a `replace`-style method hook.

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
