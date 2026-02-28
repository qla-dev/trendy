<?php

return [
    'schema' => env('DB_SCHEMA', 'dbo'),
    'table' => env('WORK_ORDERS_TABLE', 'tHF_WOEx'),
    'items_table' => env('WORK_ORDER_ITEMS_TABLE', 'tHF_WOExItem'),
    'item_resources_table' => env('WORK_ORDER_ITEM_RESOURCES_TABLE', 'tHF_WOExItemResources'),
    'reg_operations_table' => env('WORK_ORDER_REG_OPER_TABLE', 'tHF_WOExRegOper'),
    'product_structure_table' => env('WORK_ORDER_PRODUCT_STRUCTURE_TABLE', 'tHF_SetPrSt'),
    'orders_table' => env('WORK_ORDER_ORDERS_TABLE', 'tHE_Order'),
    'default_limit' => 10,
    'max_limit' => 100,
    'bom_limit' => 100,
];
