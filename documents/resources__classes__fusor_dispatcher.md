# resources/classes/fusor_dispatcher.php

## Summary
- Classes/Interfaces/Traits: fusor_dispatcher (namespace: Frytimo\Fusor\resources\classes)
- Functions/Methods: 9

## Classes
- fusor_dispatcher

## Functions and Methods
- register_listener — Register a callable listener for a named event with optional priority.
- get_listener_key — (private) Build a stable key for deduplication of listener storage.
- clear_listeners — Remove all registered listeners.
- has_listeners — Check if any listeners are registered for an event name (supports wildcards).
- event_matches — (private) fnmatch-based pattern matching with directory wildcard support.
- is_directory_wildcard_base_match_enabled — (private) Reads .env config for wildcard base match behavior.
- register_discovered_listeners — Auto-register all #[on] attributed static methods from the auto_loader.
- register_discovered_listeners_from_class_map — (private) Fallback discovery when get_attributes() is unavailable.
- dispatch — Dispatch a fusor_event to all matching listeners in priority order.

## Bootstrap
- Called from `resources/bootstrap/110-discovery.php` and `resources/bootstrap/120-dispatch.php`

## Source
- ../resources/classes/fusor_dispatcher.php
