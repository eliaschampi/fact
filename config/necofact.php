<?php

return [

    'auth' => [
        // Non-secret bcrypt hash used to equalize unknown-login password checks.
        'dummy_password_hash' => env(
            'NECOFACT_AUTH_DUMMY_PASSWORD_HASH',
            '$2y$12$BF3NPjMrhEYd.TJlnZMkA.oS4NAtlD6ovpK.wwse0Zj3OGI8v06bK',
        ),
    ],

    'seed_admin' => [
        'login' => env('NECOFACT_SEED_ADMIN_LOGIN'),
        'password' => env('NECOFACT_SEED_ADMIN_PASSWORD'),
    ],

    'business_timezone' => 'America/Lima',

    'pdf' => [
        'binary_path' => env('PDF_BINARY_PATH'),
        'timeout_ms' => (int) env('PDF_TIMEOUT_MS', 15_000),
        'max_concurrency' => (int) env('PDF_MAX_CONCURRENCY', 2),
        'max_queue' => (int) env('PDF_MAX_QUEUE', 8),
        'protocol_version' => 1,
    ],

];
