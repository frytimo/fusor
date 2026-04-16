# Fusor Source Documentation

Generated file-level references for Fusor source files, classes, and functions.

> Keep this section synchronized with the real Fusor attribute and bootstrap files whenever the project changes.

## Working Examples

See [examples/](./examples/) for complete, working PHP files that demonstrate
every Fusor attribute. Each file can be copied into `app/my_app/resources/classes/`
and discovered automatically after PHP-FPM has been restarted.

| Example | Attributes | Description |
|---------|-----------|-------------|
| [01_on_event_listener.php](./examples/01_on_event_listener.php) | `#[on]` | Named event listeners for page render events |
| [02_http_get_hook.php](./examples/02_http_get_hook.php) | `#[http_get]` | Hook GET requests (redirect, access control, logging) |
| [03_http_post_hook.php](./examples/03_http_post_hook.php) | `#[http_post]` | Hook POST submissions (validation, logging) |
| [04_on_method_enter.php](./examples/04_on_method_enter.php) | `#[on_method_enter]` | Pre-execution hooks (uopz) |
| [05_on_method_exit.php](./examples/05_on_method_exit.php) | `#[on_method_exit]` | Post-execution hooks with return value access (uopz) |
| [06_on_method_before.php](./examples/06_on_method_before.php) | `#[on_method_before]` | Alias for enter (uopz) |
| [07_on_method_after.php](./examples/07_on_method_after.php) | `#[on_method_after]` | Alias for exit (uopz) |
| [08_method_around.php](./examples/08_method_around.php) | `#[method_around]` | Wrap methods with before+after logic (uopz) |
| [09_method_replace.php](./examples/09_method_replace.php) | `#[method_replace]` | Replace method return values (uopz) |
| [10_runtime_function.php](./examples/10_runtime_function.php) | `#[runtime_function]` | Add/remove global functions at runtime (uopz) |
| [12_login_hooks.php](./examples/12_login_hooks.php) | Multiple | Complete login page customization |
| [13_logout_hooks.php](./examples/13_logout_hooks.php) | `#[http_get]` | Intercept logout, redirect, audit log |
| [14_page_render_hooks.php](./examples/14_page_render_hooks.php) | `#[on]` | Inject HTML into any page before/after render |
| [15_bridges_copy_hook.php](./examples/15_bridges_copy_hook.php) | `#[on_method_enter]`, `#[on_method_exit]` | Hook copy/delete/toggle on bridges |
| [16_custom_sort_method.php](./examples/16_custom_sort_method.php) | `#[runtime_function]` | Custom sort functions at runtime |
| [17_multi_hook_class.php](./examples/17_multi_hook_class.php) | Multiple | Single class with many hooks across FusionPBX |
| [18_freeswitch_events.php](./examples/18_freeswitch_events.php) | `#[on]` | FreeSWITCH event socket listeners |
| [19_wildcard_event_listener.php](./examples/19_wildcard_event_listener.php) | `#[on]` | Wildcard/glob event patterns |
| [20_dashboard_widget_hooks.php](./examples/20_dashboard_widget_hooks.php) | `#[http_get]`, `#[on]` | Dashboard page modification |

## Files
- [UOPZ_EXAMPLES.md](./UOPZ_EXAMPLES.md)
- [bootstrap.php](./bootstrap.md)
- [resources/fusor](./resources__fusor.md)
- [resources/attributes/http.php](./resources__attributes__http.md)
- [resources/attributes/http_get.php](./resources__attributes__http_get.md)
- [resources/attributes/http_post.php](./resources__attributes__http_post.md)
- [resources/attributes/on.php](./resources__attributes__on.md)
- [resources/attributes/on_method.php](./resources__attributes__on_method.md)
- [resources/attributes/on_method_enter.php](./resources__attributes__on_method_enter.md)
- [resources/attributes/on_method_exit.php](./resources__attributes__on_method_exit.md)
- [resources/attributes/on_method_before.php](./resources__attributes__on_method_before.md)
- [resources/attributes/on_method_after.php](./resources__attributes__on_method_after.md)
- [resources/attributes/method_around.php](./resources__attributes__method_around.md)
- [resources/attributes/method_replace.php](./resources__attributes__method_replace.md)
- [resources/attributes/runtime_function.php](./resources__attributes__runtime_function.md)
- [resources/bootstrap/100-autoloader.php](./resources__bootstrap__100-autoloader.md)
- [resources/bootstrap/110-discovery.php](./resources__bootstrap__110-discovery.md)
- [resources/bootstrap/120-dispatch.php](./resources__bootstrap__120-dispatch.md)
- [resources/bootstrap/130-hooks.php](./resources__bootstrap__130-hooks.md)
- [resources/bootstrap/140-http-route-hooks.php](./resources__bootstrap__140-http-route-hooks.md)
- [resources/classes/auto_loader.php](./resources__classes__auto_loader.md)
- [resources/classes/env_loader.php](./resources__classes__env_loader.md)
- [resources/classes/events/dtmf_option_one.php](./resources__classes__events__dtmf_option_one.md)
- [resources/classes/events/heartbeat.php](./resources__classes__events__heartbeat.md)
- [resources/classes/events/register_attempt.php](./resources__classes__events__register_attempt.md)
- [resources/classes/fusor_discovery.php](./resources__classes__fusor_discovery.md)
- [resources/classes/fusor_dispatcher.php](./resources__classes__fusor_dispatcher.md)
- [resources/classes/fusor_event.php](./resources__classes__fusor_event.md)
- [resources/classes/fusor_event_service.php](./resources__classes__fusor_event_service.md)
- [resources/classes/fusor_service.php](./resources__classes__fusor_service.md)
- [resources/classes/fusor_uopz.php](./resources__classes__fusor_uopz.md)
- [resources/classes/http_route_hook_dispatcher.php](./resources__classes__http_route_hook_dispatcher.md)
- [resources/classes/message_queue.php](./resources__classes__message_queue.md)
- [resources/classes/missed_call_webhook_listener.php](./resources__classes__missed_call_webhook_listener.md)
- [resources/classes/uuid.php](./resources__classes__uuid.md)
- [resources/interfaces/event_relay_listener.php](./resources__interfaces__event_relay_listener.md)
- [resources/interfaces/switch_listener.php](./resources__interfaces__switch_listener.md)
- [resources/service/fusor.php](./resources__service__fusor.md)

## Runtime hook examples

New example coverage is available for:

- `on_method` entry and exit hooks
- `runtime_function` helper registration
- the uopz runtime manager bootstrap flow
