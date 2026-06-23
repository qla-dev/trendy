# Order AI Digital PDF Pipeline

## Overview

Trendy now distinguishes between text-based PDFs and scanned/image-based PDFs before sending anything to AI.

Pipeline:

1. Upload document
2. Detect document profile
3. Detect PDF type (`digital`, `hybrid`, `ocr`)
4. Extract structured text for digital PDFs
5. Normalize pages into an intermediate JSON payload
6. Send structured text to AI when the PDF is digitally readable
7. Run business-rule validation
8. Save everything to `order_ai_scans` as staging data
9. Allow review before transfer to Trendy/Pantheon

## Reuse From `deklarant.ba`

The `deklarant.ba` codebase does not contain the same in-process PDF parsing layer as Trendy. Its current architecture still provided the patterns that were reused here:

- upload/orchestration is separated from extraction logic
- a `processing_mode` style decision is made before AI processing
- AI receives prepared input after document preprocessing instead of controllers building that inline
- scan results are staged and enriched before downstream business actions

Trendy reuses those architectural principles, but keeps extraction local because its order-ingest flow already had an internal PDF preparation layer.

## Trendy-Specific Implementation

### Detection

`App\Services\OrderAi\Support\OrderAiPdfTypeDetector`

- uses extracted page text density and page coverage
- classifies PDFs as `digital`, `hybrid`, or `scanned`
- returns confidence and reasoning

### Digital extraction

`App\Services\OrderAi\Support\OrderAiDigitalPdfExtractor`

- uses `smalot/pdfparser`
- extracts text page by page
- keeps reading-order text
- keeps text-matrix coordinates when available
- builds row/table candidates from positioned text
- falls back to Trendy's existing stream parser when needed

Intermediate extraction format includes:

- page text
- lines
- row/item candidates
- text coordinates
- page counts and extraction metrics

### AI handoff

`App\Services\OrderAi\Support\OrderAiDocumentPreparationService`

- keeps existing GROB-specific `ACHTUNG` cutoff logic
- builds structured JSON text for AI when the PDF is digitally readable
- falls back to raw-file/image AI input for scanned documents

### Validation

`App\Services\OrderAi\Support\OrderAiExtractionValidationService`

- prefers `NETTOPREIS` over `BRUTTOPREIS` through existing GROB post-processing plus validation
- flags subtotal mismatches against `Gesamtbetrag` / `Nettowert`
- marks hybrid/OCR results for manual review
- preserves detected document fields and validation output for audit

### Staging and telemetry

`order_ai_scans` now stores:

- `extraction_method`
- `confidence_score`
- `extraction_duration_ms`
- `ai_duration_ms`
- `validation_duration_ms`
- `raw_extracted_text`
- `extraction_payload`
- `validation_warnings`
- `validation_errors`

No comparison output or extraction audit data is written directly into Trendy production transfer tables.

## Comparison Command

Run:

```bash
php artisan trendy:compare-pdf-extraction path/to/file.pdf
```

Optional JSON output:

```bash
php artisan trendy:compare-pdf-extraction path/to/file.pdf --save-json
php artisan trendy:compare-pdf-extraction path/to/file.pdf --output=storage/app/extraction-comparisons/report.json
```

The command compares:

- legacy raw-PDF AI input
- new digital structured-text AI input

It reports:

- top-level field matches/mismatches
- line-item differences
- validation errors
- extraction/AI/validation timings
- token usage and credit spend when available
