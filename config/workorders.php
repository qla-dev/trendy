<?php

return [
    'schema' => env('DB_SCHEMA', 'dbo'),
    'table' => env('WORK_ORDERS_TABLE', 'tHF_WOEx'),
    'items_table' => env('WORK_ORDER_ITEMS_TABLE', 'tHF_WOExItem'),
    'item_resources_table' => env('WORK_ORDER_ITEM_RESOURCES_TABLE', 'tHF_WOExItemResources'),
    'reg_operations_table' => env('WORK_ORDER_REG_OPER_TABLE', 'tHF_WOExRegOper'),
    'default_limit' => 10,
    'max_limit' => 100,
];
