<?php

return [
    'schema' => env('DB_SCHEMA', 'dbo'),
    'table' => env('WORK_ORDERS_TABLE', 'tHF_WOEx'),
    'default_limit' => 10,
    'max_limit' => 100,
];
