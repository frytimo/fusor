# resources/attributes/on.php

## Summary
- Classes/Interfaces/Traits: on
- Functions/Methods: 1

## Classes
- on

## Functions and Methods
- __construct

## Example

```php
use Frytimo\Fusor\resources\attributes\on;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_listener {

    #[on(event_name: 'before_render_login', priority: 50)]
    public static function before_login(fusor_event $event): void {
        // Modify HTML or log the event here.
    }
}
```

## Related runtime attributes

- `on_method` for optional uopz-backed function and method hooks
- `override_constant` for constant overrides
- `runtime_function` for adding or removing helper functions at runtime

## Source
- ../resources/attributes/on.php
