<?php

return [
    'operation_document_type' => '6600',
    'receipt_document_type' => '6100',
    'sequence_length' => 7,
    'currency' => 'KM',
    'operation_warehouse' => env('WORK_ORDER_OPERATION_WAREHOUSE', 'RN skladište'),
    'receipt_warehouse' => env('WORK_ORDER_RECEIPT_WAREHOUSE', 'Veleprodajno skladište'),
    'department' => env('WORK_ORDER_CLOSING_DEPARTMENT', ''),
];
