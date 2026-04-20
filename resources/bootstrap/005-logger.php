<?php

// Global namespace for Fusor Resources

/**
 * Initialize Fusor logger early in the bootstrap process.
 * This allows logging to be available for all subsequent Fusor operations.
 */
require_once FUSOR_DIR . '/resources/classes/fusor_logger.php';

\Frytimo\Fusor\resources\classes\fusor_logger::initialize();
