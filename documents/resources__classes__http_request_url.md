# resources/classes/http_request_url.php

## Summary
- Classes/Interfaces/Traits: http_request_url
- Functions/Methods: 43

## Classes
- http_request_url

## Functions and Methods
- __construct
- from_request
- parse_uri
- normalize_path
- get_original_url
- get_scheme
- get_host
- get_port
- get_path
- get_fragment
- set_query
- set_query_param
- set
- unset_query_param
- delete
- get_query
- get_query_array
- get_query_param
- has_parameters
- has_query_param
- get
- request
- has
- load_post
- post
- has_post
- get_post_array
- get_input_array
- get_request_array
- get_body_array
- load_input
- input
- has_input
- load_request
- add_query_filter
- build_relative
- build_absolute
- __toString
- import_request_params
- filter_query_modifier
- default_query_filter
- sanitize_value
- get_request_array_for_source

## Purpose
Shared League URI-backed request URL adapter for Fusor HTTP lifecycle events.

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
