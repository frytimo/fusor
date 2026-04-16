# resources/attributes/http.php

## Summary
- Classes/Interfaces/Traits: http
- Functions/Methods: 4

## Classes
- http

## Functions and Methods
- __construct
- normalize_method
- normalize_stage
- normalize_path

## Purpose

Shared HTTP attribute base class used by the concrete request hook attributes.

It normalizes:

- HTTP method name
- request path
- hook stage (`before` or `after`)
- priority through the inherited `on` contract

## Used by

- `http_get`
- `http_post`

## Source
- ../resources/attributes/http.php
