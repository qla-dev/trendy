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
    // Word template: prazno - naljepnice za skladiste.docx (5952 × 3231 twips per cell).
    'labels' => ['columns'=>2, 'rows'=>5, 'width'=>104.9867, 'height'=>56.9912, 'gap_x'=>0, 'gap_y'=>0, 'margin_x'=>0.0133, 'margin_y'=>6.014, 'offset_x'=>0, 'offset_y'=>0],
];
