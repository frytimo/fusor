# resources/bootstrap/130-hooks.php

## Summary
- Classes/Interfaces/Traits: None
- Functions/Methods: None

## Classes
- None

## Functions and Methods
- None

## Purpose

This bootstrap stage performs the optional runtime auto-wiring pass for uopz-backed hooks after the normal Fusor attribute discovery is available.

## Example flow

1. Fusor bootstrap loads the project bootstrap files.
2. Standard listener discovery runs.
3. This hook stage calls the uopz manager.
4. Matching attributes are auto-wired if the extension is available.
5. If uopz is missing or incomplete, the request continues and a syslog error is written.

## Related examples

- `resources/attributes/on_method.php`
- `resources/attributes/runtime_function.php`
- `tests/fusor_uopz_smoke.php`

## Source
- ../resources/bootstrap/130-hooks.php
