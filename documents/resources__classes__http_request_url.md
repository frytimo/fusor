# resources/classes/http_request_url.php

## Summary
- Classes/Interfaces/Traits: http_request_url
- Purpose: shared League URI-backed request URL adapter for Fusor HTTP lifecycle events

## What it provides
- normalized request path handling
- safe and unsafe query parameter access
- safe and unsafe POST or input body access
- URL mutation helpers for query parameters
- a stable object exposed as `$event->url` on HTTP Fusor events

## Common methods
- `get_path()`
- `get_query_param($key, $default = null, $unsafe = false)`
- `get($key, $default = null, $unsafe = false)`
- `post($key, $default = null, $unsafe = false)`
- `has($key)`
- `has_query_param($key)`
- `has_post($key)`
- `has_parameters()`
- `build_relative()`
- `build_absolute()`

## Example

```php
use Frytimo\Fusor\resources\classes\fusor_event;

public static function on_http_event(fusor_event $event): void {
    $path = $event->url->get_path();
    $page = $event->url->get_query_param('page');
    $raw_token = $event->url->get_query_param('token', null, true);
    $username = $event->url->post('username');
}
```

## Source
- ../resources/classes/http_request_url.php
