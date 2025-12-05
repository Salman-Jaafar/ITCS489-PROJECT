<?php
// Simple configuration for local development.
// Reads from environment variables if available, otherwise falls back to sensible defaults.
return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_name' => getenv('DB_NAME') ?: 'LibraryDB',
    'debug_auth' => (getenv('DEBUG_AUTH') === 'true')
];
