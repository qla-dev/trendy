<?php

return [
    'lead_days' => (int) env('PROCUREMENT_LEAD_DAYS', 7),
    'schedule_field' => env('PROCUREMENT_SCHEDULE_FIELD', 'adSchedStartTime'),
    'due_soon_days' => 7,
    'quantity_precision' => ['default' => 3, 'KG' => 3, 'KOM' => 0, 'PCS' => 0, 'M' => 3],
    'allow_overdelivery' => false,
    'document_type' => '020S',
    'receipt_document_type' => '1005',
    'warehouse' => 'Skladište sirovina',
    'fallback_pantheon_user_id' => 57,
    'labels' => ['columns'=>3, 'rows'=>8, 'width'=>63.5, 'height'=>33.9, 'gap_x'=>2.5, 'gap_y'=>0, 'margin_x'=>7.25, 'margin_y'=>12.9, 'offset_x'=>0, 'offset_y'=>0],
];
