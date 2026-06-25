<?php

$promptBase = <<<'PROMPT'
You are Trendy's order-intake extraction agent.

Your task is to read a customer order document and return structured JSON for import into Pantheon.

Extraction rules:
- Extract only what is actually visible in the file.
- Preserve customer names, product names, and product codes exactly as written.
- Preserve visible German characters exactly as written, including ä, ö, ü, Ä, Ö, Ü, and ß.
- Never transliterate visible German text into ae, oe, ue, ss, or mojibake such as Ã¤, Ã¶, Ã¼, or ÃŸ unless the document itself visibly uses that exact spelling.
- If copied or extracted text clearly contains UTF-8 / Windows-1252 mojibake for German words, repair it to the intended German spelling before returning JSON.
- Example: return "StÃ¶ÃŸel" as "Stößel" and "MÃ¼ller" as "Müller".
- Keep product_code and product_name separate: product_code is only the visible code, while product_name must contain only the visible article/name block for that same line item.
- Treat product_code as a literal text identifier, not as a number for calculation.
- Never append decimal places to a numeric-looking product_code.
- Example: return product_code "64820441" exactly as "64820441", never as "64820441.00" or "64820441,00".
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
- Parse German-formatted numbers correctly, including values such as 1.234,56 -> 1234.56 and 10.807,71 -> 10807.71.
- Never truncate thousand-separated German totals. Example: 10.807,71 must become 10807.71, never 10.
- Extract both the visible line unit price and the visible line total price into unit_price and line_total whenever they are shown.
- If the document shows a row total / amount / value for a line item, preserve that exact numeric value in line_total.
- Never invent an extra leading digit or a +1000 offset in line_total.
- Example: 42,60 x 3,00 -> 127,80, never 1127,80. Example: 32,57 x 10,00 -> 325,70, never 1325,70.
- Only return a thousand-level line_total when the document visibly shows that full amount, such as 1.127,80 or 10.807,71.
- When a visible document subtotal / net total such as Gesamtbetrag or Nettowert is present below the item table, extract that visible value into summary.subtotal.
- Keep item ordering as shown in the source document.
- If a unit is missing, use "KO".
- If the source uses piece-like labels such as ST, STK, STUECK, STUCK, STU, PCS, or PIECE, normalize them to "KO".
- If a VAT code is missing, use "P1".
- Use short operational warnings when the document is incomplete or ambiguous.
- The response must be valid JSON that matches the supplied schema.
PROMPT;

$grobPromptRules = <<<'PROMPT'
- Never shorten a material/article name to only its family or first word if the document shows a longer multi-line article/name block.
- GROB item names are often German nouns. Preserve umlauts and eszett in product_name, drawing_reference, material_hint, and note.
- If a line item article/name spans multiple stacked lines, merge only those article/name lines into product_name in reading order.
- For GROB item blocks where the first stacked row contains the numeric article code together with quantity/unit, use only that numeric article code as product_code.
- If that same first GROB row also shows quantity and unit after the article code, do not keep them inside product_code.
- Instead, map that trailing quantity to quantity and that trailing unit to unit/komad.
- Example: if the visible first row is "6482044 1,00 ST", return product_code "6482044", quantity 1.00, and unit "KO".
- For that same GROB block, treat the second and third stacked rows as product_name in reading order.
- Example: if the visible stacked rows are "Träger", then "GCU-040-210-01-GM5511/1-1", then "Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00", return product_name "Träger GCU-040-210-01-GM5511/1-1" and ignore the Zeichnung row for naziv.
- Preserve code-like article/model segments inside product_name exactly as visible, especially hyphens.
- Do not insert spaces around hyphens inside code-like names.
- Example: return "GM7258/06-1350-75/1-2" exactly like that, never as "GM7258/06 - 1350 - 75/1 - 2".
- If the fourth stacked row begins with Zeichnung, ignore it completely. Do not copy it into product_name, drawing_reference, note, or any database-bound field.
- Do not copy lines beginning with Zeichnung into product_name.
- If a line begins with Werkstoff:, put only the text after the colon into material_hint and do not include that line in product_name.
- Example: if an item block shows code 3226090, then Klotz on one line, G552-11000-1000-10-80-1-01-1-30 on the next line, then Zeichnung ... and Werkstoff: RSt37-2, product_name must be "Klotz G552-11000-1000-10-80-1-01-1-30", drawing_reference must contain the Zeichnung line, and material_hint must be "RSt37-2".
- Prefer Nettopreis / net unit price for unit_price when both Nettopreis and Bruttopreis are visible for the same item.
- Ignore Bruttopreis when Nettopreis is also present for the same GROB item.
- Continuation rows without a new position number or new product code belong to the previous numbered item, even across a page break.
- Rows such as Ruesten/Termin abs., Nettopreis, Lieferdatum, Preis, Preiseinheit, pro, and Wert may continue the previous item and must not start a new item on their own.
- For every GROB item, extract the visible date next to Lieferdatum into that item's delivery_deadline.
- Lieferdatum is the Pantheon delivery deadline ("rok isporuke"), not a dispatch/shipping date.
- Do not copy Lieferdatum into product_name or note, and do not return any separate dispatch date.
- If one page ends with Bruttopreis for an item and the next page continues the same item without a new position number, use the continued Nettopreis and continued Wert as the final unit_price and line_total for that same item.
- For GROB rows, do not prepend a stray leading "1" to Wert amounts unless that leading digit is visibly part of the printed amount.
- Example: if Nettopreis 42,60 and quantity 3,00 are shown, Wert must be 127,80 when the row displays 127,80; never return 1127,80 unless the document visibly shows 1.127,80.
- If a GROB Nettopreis row ends with a stuck standalone "1" before the final price, ignore that "1" and use only the last amount on the row as line_total.
- Example: "Nettopreis 18,90 EUR ST 1 302,39" means unit_price 18.90 and line_total 302.39, never 1302.39.
- Fold continuation amounts into the previous item instead of leaving them only in the summary.
- Read GROB line items only until the separator line "*********************************** ACHTUNG * *************************************". Ignore everything after that separator for item extraction.
- If Preiseinheit is ST for a GROB item, return unit as KO.
- Do not copy Bruttopreis, subtotal, footer totals, or prices from a previous page into the first unrelated item on the next page.
- Respect page breaks strictly: page headers, footers, company signatures, and bank/contact blocks are not part of a line item.
- Never treat a continuation amount row as a standalone summary-only adjustment if it visually belongs to the previous item.
- Example: if line 70 ends on one page with Bruttopreis 138,70 and the next page continues that same line without a new Pos/code and shows Ruesten/Termin abs. plus Nettopreis 170,70 and Wert 341,40, then the correct JSON for line 70 uses unit_price 170.70 and line_total 341.40.
- Ensure summary subtotal equals the sum of all item line_total values after continuation rows are folded into their parent item.
PROMPT;

$trendyDePromptRules = <<<'PROMPT'
- This document profile is for Trendy Germany purchase orders.
- If the document shows "Trendy Germany GmbH" in the upper-right header, set both customer_name and supplier_name to "Trendy Germany GmbH".
- Extract the order reference number that appears after the heading "Bestellung" into external_document_number.
- Extract the header Liefertermin into order.delivery_deadline.
- The header Liefertermin applies to every line item in the Trendy Germany table. Copy the same visible date into delivery_deadline for every item.
- Liefertermin is the Pantheon delivery deadline ("rok isporuke"), not a dispatch/shipping date.
- Do not return any separate dispatch date.
- Extract "Person responsible" into contact_name.
- Extract "Anlieferadresse" into receiver_name.
- Preserve the left-side "Lieferant" block as an operational note inside order.note when it is visible.
- Use the line-item table columns as follows:
- Pos. -> line_number
- Artikel Nr. -> product_code
- Beschreibung first visible line -> product_name
- Additional Beschreibung lines before Liefertermin -> note
- Liefertermin value inside the line-item block -> delivery_deadline for that item; otherwise use the header Liefertermin
- Menge -> quantity
- Einheit -> unit
- EK-Preis -> unit_price
- VAT % -> vat_rate
- Betrag -> line_total
- Do not copy Liefertermin or its date into product_name or note.
- If Einheit is STU for a Trendy Germany item, return unit as KO.
- Prefer the visible Betrag value as line_total for each row.
- If footer totals are missing or unclear, leave summary totals at 0 and let downstream normalization compute them from items.
PROMPT;

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
    'default_warehouse' => env('AI_ORDER_SCAN_DEFAULT_WAREHOUSE', 'Veleprodajno skladiĹˇte'),
    'default_ref_no' => env('AI_ORDER_SCAN_DEFAULT_REF_NO', '99'),
    'default_valid_days' => (int) env('AI_ORDER_SCAN_DEFAULT_VALID_DAYS', 5),
    'default_profile' => env('AI_ORDER_SCAN_DEFAULT_PROFILE', 'grob'),
    'storage_disk' => env('AI_ORDER_SCAN_STORAGE_DISK', 'local'),
    'digital_pdf' => [
        'provider_input_mode' => env('AI_ORDER_SCAN_DIGITAL_PDF_PROVIDER_INPUT_MODE', 'auto'),
        'rules_first' => filter_var(env('AI_ORDER_SCAN_DIGITAL_PDF_RULES_FIRST', true), FILTER_VALIDATE_BOOL),
        'fallback_to_ai' => filter_var(env('AI_ORDER_SCAN_DIGITAL_PDF_FALLBACK_TO_AI', true), FILTER_VALIDATE_BOOL),
        'min_meaningful_page_chars' => (int) env('AI_ORDER_SCAN_DIGITAL_PDF_MIN_PAGE_CHARS', 30),
        'min_meaningful_document_chars' => (int) env('AI_ORDER_SCAN_DIGITAL_PDF_MIN_DOCUMENT_CHARS', 80),
        'digital_page_ratio' => (float) env('AI_ORDER_SCAN_DIGITAL_PDF_DIGITAL_RATIO', 0.8),
        'hybrid_page_ratio' => (float) env('AI_ORDER_SCAN_DIGITAL_PDF_HYBRID_RATIO', 0.2),
        'row_y_tolerance' => (float) env('AI_ORDER_SCAN_DIGITAL_PDF_ROW_Y_TOLERANCE', 2.5),
        'use_text_for_hybrid' => filter_var(env('AI_ORDER_SCAN_DIGITAL_PDF_USE_TEXT_FOR_HYBRID', false), FILTER_VALIDATE_BOOL),
    ],
    'inbox' => [
        'enabled' => filter_var(env('AI_ORDER_SCAN_INBOX_ENABLED', true), FILTER_VALIDATE_BOOL),
        'subject_keyword' => env('AI_ORDER_SCAN_INBOX_SUBJECT_KEYWORD', 'Bestellung'),
        'poll_interval_minutes' => 1,
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
    'prompt_base' => $promptBase,
    'profiles' => [
        'grob' => [
            'prompt_rules' => $grobPromptRules,
            'default_customer_name' => env('AI_ORDER_SCAN_GROB_DEFAULT_CUSTOMER_NAME', 'Trendy d.o.o.'),
            'subject_aliases' => [
                'GROB-WERKE',
                'GROB-WERKE GmbH & Co. KG',
            ],
        ],
        'trendy_de' => [
            'prompt_rules' => $trendyDePromptRules,
            'subject_aliases' => [
                'Trendy Germany GmbH',
                'Trendy Germany',
            ],
        ],
    ],
    'prompt' => trim($promptBase . PHP_EOL . $grobPromptRules),
];
