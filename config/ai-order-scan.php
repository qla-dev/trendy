<?php

return [
    'provider' => env('AI_ORDER_SCAN_PROVIDER', 'mock'),
    'model' => env('AI_ORDER_SCAN_MODEL', 'gpt-5'),
    'timeout' => (int) env('AI_ORDER_SCAN_TIMEOUT', 120),
    'auto_transfer' => filter_var(env('AI_ORDER_SCAN_AUTO_TRANSFER', false), FILTER_VALIDATE_BOOL),
    'credit_rate' => (float) env('AI_ORDER_SCAN_CREDIT_RATE', 1),
    'monthly_credit_limit' => (float) env('AI_ORDER_SCAN_MONTHLY_CREDIT_LIMIT', 10000),
    'monthly_token_limit' => (int) env('AI_ORDER_SCAN_MONTHLY_TOKEN_LIMIT', 10000),
    'storage_directory' => env('AI_ORDER_SCAN_STORAGE_DIRECTORY', 'order-ai-scans'),
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
    'inbox' => [
        'enabled' => filter_var(env('AI_ORDER_SCAN_INBOX_ENABLED', true), FILTER_VALIDATE_BOOL),
        'subject_keyword' => env('AI_ORDER_SCAN_INBOX_SUBJECT_KEYWORD', 'Bestellung'),
        'poll_interval_minutes' => max(1, (int) env('AI_ORDER_SCAN_INBOX_POLL_INTERVAL', 15)),
        'queue_connection' => env('AI_ORDER_SCAN_INBOX_QUEUE_CONNECTION', 'database_ai_inbox'),
        'queue_name' => env('AI_ORDER_SCAN_INBOX_QUEUE_NAME', 'ai-inbox'),
        'enforce_sender_whitelist' => env('AI_ORDER_SCAN_INBOX_ENFORCE_SENDER_WHITELIST'),
        'allowed_senders' => array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,;]+/', (string) env('AI_ORDER_SCAN_INBOX_ALLOWED_SENDERS', '')) ?: []
        ))),
        'imap' => [
            'host' => env('AI_ORDER_SCAN_INBOX_IMAP_HOST', env('IMAP_HOST', '')),
            'port' => (int) env('AI_ORDER_SCAN_INBOX_IMAP_PORT', env('IMAP_PORT', 993)),
            'protocol' => env('AI_ORDER_SCAN_INBOX_IMAP_PROTOCOL', env('IMAP_PROTOCOL', 'imap')),
            'encryption' => env('AI_ORDER_SCAN_INBOX_IMAP_ENCRYPTION', env('IMAP_ENCRYPTION', 'ssl')),
            'validate_cert' => filter_var(env('AI_ORDER_SCAN_INBOX_IMAP_VALIDATE_CERT', env('IMAP_VALIDATE_CERT', true)), FILTER_VALIDATE_BOOL),
            'username' => env('AI_ORDER_SCAN_INBOX_IMAP_USERNAME', 'colakovic.vedad@qla.dev'),
            'password' => env('AI_ORDER_SCAN_INBOX_IMAP_PASSWORD', env('IMAP_PASSWORD', '')),
            'authentication' => env('AI_ORDER_SCAN_INBOX_IMAP_AUTHENTICATION', env('IMAP_AUTHENTICATION', null)),
            'timeout' => (int) env('AI_ORDER_SCAN_INBOX_IMAP_TIMEOUT', 30),
            'open' => array_filter([
                'DISABLE_AUTHENTICATOR' => env('AI_ORDER_SCAN_INBOX_IMAP_DISABLE_AUTHENTICATOR'),
            ]),
        ],
        'folders' => [
            'source' => env('AI_ORDER_SCAN_INBOX_SOURCE_FOLDER', 'INBOX'),
            'processed' => env('AI_ORDER_SCAN_INBOX_PROCESSED_FOLDER', 'INBOX/Processed'),
            'review' => env('AI_ORDER_SCAN_INBOX_REVIEW_FOLDER', 'INBOX/Review'),
        ],
    ],
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
- Keep product_code and product_name separate: product_code is only the visible code, while product_name must contain the full visible material/article description for that same line item.
- Never shorten a material/article name to only its family or first word if the document shows a longer multi-line description.
- If a line item description spans multiple stacked lines, merge all description lines that belong to that item into product_name in reading order.
- Preserve the full descriptive material text, including drawing/designation, revision, and material/werkstoff details, whenever they are visibly part of the same item block.
- If a value is missing or uncertain, return an empty string for text fields or 0 for numeric fields.
- Never invent product codes, prices, delivery dates, or document numbers.
- Prefer the buyer/customer name from the document header.
- Extract the supplier / sender / issuer name from the top header into supplier_name.
- Extract the total page count of the uploaded document into page_count.
- page_count must be the total number of pages in the uploaded file, not the current page number and not a subsection-local page counter.
- Example: if the footer/header says "Seite 4 von 6", then page_count is 6.
- Keep buyer/customer and supplier/sender separate when both are visible.
- Prefer a purchase-order / narudzba / order reference number when present.
- Normalize quantities, prices, rebates, and VAT rates into numeric values.
- Extract both the visible line unit price and the visible line total price into unit_price and line_total whenever they are shown.
- If the document shows a row total / amount / value for a line item, preserve that exact numeric value in line_total.
- Prefer Nettopreis / net unit price for unit_price when both Nettopreis and Bruttopreis are visible for the same item.
- Continuation rows without a new position number or new product code belong to the previous numbered item, even across a page break.
- Rows such as Ruesten/Termin abs., Nettopreis, Lieferdatum, Preis, Preiseinheit, pro, and Wert may continue the previous item and must not start a new item on their own.
- If one page ends with Bruttopreis for an item and the next page continues the same item without a new position number, use the continued Nettopreis and continued Wert as the final unit_price and line_total for that same item.
- Fold continuation amounts into the previous item instead of leaving them only in the summary.
- Do not copy Bruttopreis, subtotal, footer totals, or prices from a previous page into the first unrelated item on the next page.
- Respect page breaks strictly: page headers, footers, company signatures, and bank/contact blocks are not part of a line item.
- Never treat a continuation amount row as a standalone summary-only adjustment if it visually belongs to the previous item.
- Example: if line 70 ends on one page with Bruttopreis 138,70 and the next page continues that same line without a new Pos/code and shows Ruesten/Termin abs. plus Nettopreis 170,70 and Wert 341,40, then the correct JSON for line 70 uses unit_price 170.70 and line_total 341.40.
- Ensure summary subtotal equals the sum of all item line_total values after continuation rows are folded into their parent item.
- Keep item ordering as shown in the source document.
- If a unit is missing, use "KO".
- If the source uses piece-like labels such as ST, STK, STUECK, STUCK, PCS, or PIECE, normalize them to "KO".
- If a VAT code is missing, use "P1".
- Use short operational warnings when the document is incomplete or ambiguous.
- The response must be valid JSON that matches the supplied schema.
PROMPT,
];
