<?php

return [
    'operation_document_type' => '6600',
    'receipt_document_type' => '6100',
    'scrap_receipt_document_type' => '7100',
    'sequence_length' => 7,
    'currency' => 'KM',
    'operation_warehouse' => env('WORK_ORDER_OPERATION_WAREHOUSE', 'RN skladište'),
    'receipt_warehouse' => env('WORK_ORDER_RECEIPT_WAREHOUSE', 'Veleprodajno skladište'),
    'scrap_receipt_warehouse' => env('WORK_ORDER_SCRAP_RECEIPT_WAREHOUSE', 'Skladište škarta'),
    'department' => env('WORK_ORDER_CLOSING_DEPARTMENT', ''),
];
