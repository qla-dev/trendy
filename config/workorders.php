<?php

return [
    'schema' => env('DB_SCHEMA', 'dbo'),
    'table' => env('WORK_ORDERS_TABLE', 'tHF_WOEx'),
    'items_table' => env('WORK_ORDER_ITEMS_TABLE', 'tHF_WOExItem'),
    'catalog_items_table' => env('WORK_ORDER_CATALOG_ITEMS_TABLE', 'tHE_SetItem'),
    'stock_table' => env('WORK_ORDER_STOCK_TABLE', 'tHE_Stock'),
    'item_resources_table' => env('WORK_ORDER_ITEM_RESOURCES_TABLE', 'tHF_WOExItemResources'),
    'reg_operations_table' => env('WORK_ORDER_REG_OPER_TABLE', 'tHF_WOExRegOper'),
    'product_structure_table' => env('WORK_ORDER_PRODUCT_STRUCTURE_TABLE', 'tHF_SetPrSt'),
    'orders_table' => env('WORK_ORDER_ORDERS_TABLE', 'tHE_Order'),
    'order_items_table' => env('WORK_ORDER_ORDER_ITEMS_TABLE', 'tHE_OrderItem'),
    'work_order_order_item_link_table' => env('WORK_ORDER_ORDER_ITEM_LINK_TABLE', 'vHF_LinkWOExOrderItem'),
    'default_limit' => 10,
    'max_limit' => 100,
    'bom_limit' => 100,
];
