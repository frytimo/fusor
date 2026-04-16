# resources/classes/http_route_hook_dispatcher.php

## Summary
- Classes/Interfaces/Traits: http_route_hook_dispatcher (namespace: Frytimo\Fusor\resources\classes)
- Functions/Methods: 4
- Notes: HTTP lifecycle events include a shared URL helper object plus filtered request arrays for safe access.

## Classes
- http_route_hook_dispatcher

## Functions and Methods
- dispatch_request_hooks — Main entry point. Dispatches before/after HTTP hooks for GET and POST requests.
- resolve_request_fingerprint — (private) Creates a unique fingerprint per request to prevent duplicate dispatch.
- resolve_request_path — (private) Extracts and normalizes the request path from server globals.
- normalize_path — (private) Delegates to http_request_url::normalize_path.

## Bootstrap
- Called from `resources/bootstrap/140-http-route-hooks.php`

## Source
- ../resources/classes/http_route_hook_dispatcher.php
