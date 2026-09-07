<?php

return [
    'enabled' => env('EXCESS_STOCK_ENABLED', false),
    'assign_on_work_order_create' => env('EXCESS_STOCK_ASSIGN_ON_WORK_ORDER_CREATE', false),
    // Testna is the only permitted apply target until this feature is signed off.
    'backfill_connection' => env('EXCESS_STOCK_BACKFILL_CONNECTION', 'work_order_target'),
    'allow_apply' => env('EXCESS_STOCK_ALLOW_APPLY', false),
    'warehouse' => env('EXCESS_STOCK_WAREHOUSE', 'Skladište dodatnih sirovina'),
    'target_date' => env('EXCESS_STOCK_TARGET_DATE', '2026-12-15'),
    'max_materials_per_work_order' => (int) env('EXCESS_STOCK_MAX_MATERIALS_PER_WORK_ORDER', 5),
    // Article sales prices are in EUR while issued material prices are in KM.
    // Convert the sales-price cap to KM before comparing it to material value.
    'assignment_mode' => env('EXCESS_STOCK_ASSIGNMENT_MODE', 'sales_price_percent'),
    'sales_price_percent' => env('EXCESS_STOCK_SALES_PRICE_PERCENT', '0.07'),
    'sales_price_eur_to_km_rate' => env('EXCESS_STOCK_SALES_PRICE_EUR_TO_KM_RATE', '1.958'),
    'reservation_marker' => 'Rezervacija dodatnih sirovina',
    'document_marker' => 'RAZDUZENJE_DODATNIH_SIROVINA_6400',
    'notification_lookback_days' => (int) env('EXCESS_STOCK_NOTIFICATION_LOOKBACK_DAYS', 2),
    // Kept separate from the AI scanner so this workflow can be routed to
    // its own responsible person without changing any regular notifications.
    'notification_recipient' => env(
        'EXCESS_STOCK_NOTIFICATION_RECIPIENT',
        env('AI_ORDER_SCAN_TRANSFER_FAILURE_RECIPIENT', 'colakovicvedad1607@gmail.com')
    ),
];
