<?php

return [
    'schema' => env('DB_SCHEMA', 'dbo'),
    'table' => env('WORK_ORDERS_TABLE', 'tHF_WOEx'),
    'default_limit' => (int) env('WORK_ORDERS_DEFAULT_LIMIT', 100),
    'max_limit' => (int) env('WORK_ORDERS_MAX_LIMIT', 500),
];
