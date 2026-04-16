# resources/attributes/http_post.php

## Summary
- Classes/Interfaces/Traits: http_post
- Functions/Methods: 1

## Classes
- http_post

## Functions and Methods
- __construct

## Purpose

Registers a POST lifecycle hook for a matching request path.

## Example

```php
use Frytimo\Fusor\resources\attributes\http_post;
use Frytimo\Fusor\resources\classes\fusor_event;

class my_post_hooks {

    #[http_post('/resources/login.php', stage: 'after')]
    public static function after_login(fusor_event $event): void {
        // Inspect the finalized POST result.
    }
}
```

## Source
- ../resources/attributes/http_post.php
