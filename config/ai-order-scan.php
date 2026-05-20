<?php

return [
    'provider' => env('AI_ORDER_SCAN_PROVIDER', 'mock'),
    'model' => env('AI_ORDER_SCAN_MODEL', 'gpt-5'),
    'timeout' => (int) env('AI_ORDER_SCAN_TIMEOUT', 120),
    'auto_transfer' => filter_var(env('AI_ORDER_SCAN_AUTO_TRANSFER', false), FILTER_VALIDATE_BOOL),
    'credit_rate' => (float) env('AI_ORDER_SCAN_CREDIT_RATE', 1),
    'default_doc_type' => env('AI_ORDER_SCAN_DEFAULT_DOC_TYPE', '0110'),
    'default_currency' => env('AI_ORDER_SCAN_DEFAULT_CURRENCY', 'KM'),
    'default_vat_code' => env('AI_ORDER_SCAN_DEFAULT_VAT_CODE', 'P1'),
    'default_vat_rate' => (float) env('AI_ORDER_SCAN_DEFAULT_VAT_RATE', 17),
    'default_unit' => env('AI_ORDER_SCAN_DEFAULT_UNIT', 'KO'),
    'default_way_of_sale' => env('AI_ORDER_SCAN_DEFAULT_WAY_OF_SALE', 'D'),
    'default_warehouse' => env('AI_ORDER_SCAN_DEFAULT_WAREHOUSE', 'Veleprodajno skladište'),
    'default_ref_no' => env('AI_ORDER_SCAN_DEFAULT_REF_NO', '99'),
    'default_valid_days' => (int) env('AI_ORDER_SCAN_DEFAULT_VALID_DAYS', 5),
    'storage_disk' => env('AI_ORDER_SCAN_STORAGE_DISK', 'local'),
    'storage_directory' => 'order-ai-scans',
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'http_referer' => env('OPENROUTER_HTTP_REFERER', env('APP_URL')),
        'title' => env('OPENROUTER_TITLE', env('APP_NAME')),
    ],
    'prompt' => <<<'PROMPT'
You are Trendy's order-intake extraction agent.

Your task is to read a customer order document and return structured JSON for import into Pantheon.

Extraction rules:
- Extract only what is actually visible in the file.
- Preserve customer names, product names, and product codes exactly as written.
- If a value is missing or uncertain, return an empty string for text fields or 0 for numeric fields.
- Never invent product codes, prices, delivery dates, or document numbers.
- Prefer the buyer/customer name from the document header.
- Prefer a purchase-order / narudzba / order reference number when present.
- Normalize quantities, prices, rebates, and VAT rates into numeric values.
- Keep item ordering as shown in the source document.
- If a unit is missing, use "KO".
- If the source uses piece-like labels such as ST, STK, STUECK, STUCK, PCS, or PIECE, normalize them to "KO".
- If a VAT code is missing, use "P1".
- Use short operational warnings when the document is incomplete or ambiguous.
- The response must be valid JSON that matches the supplied schema.
PROMPT,
];
