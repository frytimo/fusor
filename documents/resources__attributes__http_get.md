# resources/attributes/http_get.php

## Summary
- Classes/Interfaces/Traits: http_get
- Functions/Methods: 1

## Classes
- http_get

## Functions and Methods
- __construct

## Purpose

Registers a GET lifecycle hook for a matching request path.

## Example

```php
use Frytimo\Fusor\resources\attributes\http_get;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_get_hooks {

    #[http_get('/resources/login.php', stage: 'before')]
    public static function before_login(fusor_event $event): void {
        // Handle the request before rendering.
    }
}
```

## Source
- ../resources/attributes/http_get.php
