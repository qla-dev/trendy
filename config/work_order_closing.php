<?php

return [
    'work_order_2005_flow_start_date' => env('WORK_ORDER_2005_FLOW_START_DATE', '2026-07-21 00:00:00'),
    'raw_material_warehouse' => env('WORK_ORDER_RAW_MATERIAL_WAREHOUSE', 'Skladište sirovina'),
    // Pantheon warehouse subject name, configurable rather than a volatile QId.
    'work_in_progress_warehouse' => env('WORK_ORDER_WIP_WAREHOUSE', 'Proizvodnja u toku - skladište'),
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
