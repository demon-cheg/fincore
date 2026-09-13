<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),

    'port' => (int) env('RABBITMQ_PORT', 5672),

    'user' => env('RABBITMQ_USER', 'fincore'),

    'password' => env('RABBITMQ_PASSWORD', 'fincore_dev'),

    'vhost' => env('RABBITMQ_VHOST', '/'),

    'exchange' => env(
        'RABBITMQ_EXCHANGE',
        'fincore.events'
    ),
    'audit' => [
    'queue' => env('RABBITMQ_AUDIT_QUEUE', 'fincore.audit'),
    'binding' => env('RABBITMQ_AUDIT_BINDING', 'transfer.*'),

    'retry_exchange' => env(
        'RABBITMQ_AUDIT_RETRY_EXCHANGE',
        'fincore.audit.retry.exchange'
    ),

    'retry_queue' => env(
        'RABBITMQ_AUDIT_RETRY_QUEUE',
        'fincore.audit.retry'
    ),

    'retry_delay_ms' => (int) env(
        'RABBITMQ_AUDIT_RETRY_DELAY_MS',
        5000
    ),

    'dead_letter_exchange' => env(
        'RABBITMQ_AUDIT_DLX',
        'fincore.audit.dlx'
    ),

    'dead_letter_queue' => env(
        'RABBITMQ_AUDIT_DLQ',
        'fincore.audit.dlq'
    ),

    'max_retries' => (int) env(
        'RABBITMQ_AUDIT_MAX_RETRIES',
        3
    ),
],
];