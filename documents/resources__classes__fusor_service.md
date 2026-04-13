# resources/classes/fusor_service.php

## Summary
- Classes/Interfaces/Traits: fusor_service
- Functions/Methods: 43

## Classes
- fusor_service

## Functions and Methods
- display_version
- set_command_options
- set_switch_address
- set_switch_port
- disable_pcntl
- enable_map_dump
- run
- reload_settings
- initialize_shared_memory
- ensure_composer_autoload
- publish_listener_map_to_shared_memory
- rebuild_listener_maps
- get_autoload
- load_attribute_switch_listeners
- load_attribute_switch_listeners_by_reflection
- include_fusor_class_files_for_discovery
- is_namespaced_fusor_class_file
- discover_fusor_classes
- register_switch_attribute_listener
- load_interface_switch_listeners
- index_listener_event
- pcntl_enabled
- handle_sigchld
- accept_connection
- fork_connection_worker
- attach_connection
- run_connection_worker_loop
- handle_connection_data
- extract_message
- parse_headers
- parse_event_body
- process_message
- resolve_event_name
- trigger_switch_event
- invoke_class_listener
- register_event_listener_class
- maybe_send_initial_commands
- send_preloaded_event_subscriptions
- send_command
- execute_app
- prune_connections
- disconnect
- shutdown_listener_socket

## Source
- ../resources/classes/fusor_service.php
