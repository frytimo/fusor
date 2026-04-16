# resources/classes/auto_loader.php

## Summary
- Classes/Interfaces/Traits: auto_loader
- Functions/Methods: 42

## Classes
- auto_loader

## Functions and Methods
- __construct
- load_cache
- reload_classes
- rebuild_traits_from_classes
- update_cache
- log
- update
- clear_cache
- get_attributes
- get_attributes_by_name
- get_class_attributes
- get_function_attributes
- get_method_attributes
- get_method_list
- get_constant_attributes
- get_property_attributes
- default_attribute_map
- reload_attributes
- parse_attribute_file
- add_attributes_to_target
- consume_attribute_group
- split_top_level_csv
- next_significant_index
- extract_method_modifiers
- extract_visibility
- is_namespace_token
- get_short_name
- get_class_list
- get_interface_list
- get_direct_implementers
- get_child_interfaces
- get_interfaces
- get_traits
- loader
- project_path
- project_path_from_env
- class_search_paths
- configured_search_patterns_from_env
- normalize_search_pattern
- cache_enabled_from_env
- env_settings
- cache_file_path

## Runtime behavior note

The current auto-loader implementation is intentionally in-memory only.
It rebuilds its internal class, interface, inheritance, trait, and attribute maps from source files for the request and no longer persists those maps to APCu or disk.

Legacy compatibility methods such as `load_cache`, `update_cache`, and `clear_cache` still exist, but they no longer provide persistent caching.

## Source
- ../resources/classes/auto_loader.php
