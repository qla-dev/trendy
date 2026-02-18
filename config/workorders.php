<?php

return [
    'schema' => env('DB_SCHEMA', 'dbo'),
    'table' => env('WORK_ORDERS_TABLE', 'tHF_WOEx'),
    'items_table' => env('WORK_ORDER_ITEMS_TABLE', 'tHF_WOExItem'),
    'default_limit' => 10,
    'max_limit' => 100,
];
