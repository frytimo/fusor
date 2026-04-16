# resources/classes/fusor_uopz.php

## Summary
- Classes/Interfaces/Traits: fusor_uopz
- Functions/Methods: 16+

## Classes
- fusor_uopz

## Key Responsibilities
- Discover uopz-backed attributes from the Fusor registry
- Install entry hooks and exit wrappers
- Apply constant overrides
- Register or remove runtime helper functions
- Fail open and write syslog errors when uopz is missing or incomplete

## Bootstrap integration

This class is called from `resources/bootstrap/130-hooks.php` after the normal discovery stage.

## Verified demo example

```bash
php /var/www/fusionpbx/tests/fusor_uopz_smoke.php
```

Current verified behaviors in this workspace:

- constant override is active
- exit hook decoration is active
- runtime function registration is active
- syslog logging is active

## Related example classes

- `app/fusor_example/resources/classes/fusor_uopz_demo.php`
- `app/fusor/resources/attributes/on_method.php`
- `app/fusor/resources/attributes/override_constant.php`
- `app/fusor/resources/attributes/runtime_function.php`

## Source
- ../resources/classes/fusor_uopz.php
