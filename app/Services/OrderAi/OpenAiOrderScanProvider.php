<?php

namespace App\Services\OrderAi;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use App\Services\OrderAi\Support\OrderAiDocumentPreparationService;
use App\Services\OrderAi\Support\OrderAiResponseSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiOrderScanProvider implements OrderAiScanProvider
{
    public function supportsLiveTransfer(): bool
    {
        return true;
    }

    public function scan(OrderAiScan $scan): array
    {
        $model = trim((string) config('ai-order-scan.model', 'gpt-5'));
        $apiKey = trim((string) config('ai-order-scan.openai.api_key'));

        if ($apiKey === '') {
            throw $this->newProviderException(
                'OPENAI_API_KEY nije postavljen.',
                $this->buildFailureContext($model)
            );
        }

        $disk = (string) config('ai-order-scan.storage_disk', 'local');

        if (!Storage::disk($disk)->exists($scan->source_file_path)) {
            throw $this->newProviderException(
                'Ucitani dokument nije pronadjen na disku.',
                $this->buildFailureContext($model)
            );
        }

        $bytes = Storage::disk($disk)->get($scan->source_file_path);
        $mime = $this->normalizeDocumentMime(
            (string) ($scan->source_mime_type ?: 'application/octet-stream'),
            (string) ($scan->source_file_name ?: ''),
            $bytes
        );
        $baseUrl = rtrim((string) config('ai-order-scan.openai.base_url', 'https://api.openai.com/v1'), '/');
        $prompt = trim((string) ($scan->request_prompt ?: config('ai-order-scan.prompt')));
        $preparationStartedAt = microtime(true);
        $preparedDocument = app(OrderAiDocumentPreparationService::class)->prepareDocument(
            (string) ($scan->document_profile ?? ''),
            (string) ($scan->source_file_name ?? 'document'),
            $mime,
            $bytes
        );
        $extractionDurationMs = max(
            (int) round((microtime(true) - $preparationStartedAt) * 1000),
            (int) ($preparedDocument['extraction_duration_ms'] ?? 0)
        );
        $documentInput = $this->buildDocumentInput($scan, $mime, $bytes, $preparedDocument);
        $aiStartedAt = microtime(true);

        $response = Http::withToken($apiKey)
            ->timeout((int) config('ai-order-scan.timeout', 120))
            ->acceptJson()
            ->post($baseUrl . '/responses', [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Extract the order from this document and return JSON that matches the provided schema.',
                            ],
                            ...$documentInput,
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'trendy_order_scan',
                        'strict' => true,
                        'schema' => OrderAiResponseSchema::definition(),
                    ],
                ],
            ]);
        $aiDurationMs = (int) round((microtime(true) - $aiStartedAt) * 1000);

        if (!$response->successful()) {
            $errorPayload = $response->json();

            if (!is_array($errorPayload)) {
                $errorPayload = [
                    'http_status' => $response->status(),
                    'body' => $response->body(),
                ];
            } else {
                $errorPayload['_http_status'] = $response->status();
            }

            throw $this->newProviderException(
                'OpenAI odgovor nije uspjesan: ' . $response->body(),
                $this->buildFailureContext(
                    $model,
                    $errorPayload,
                    (string) ($errorPayload['id'] ?? '')
                )
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw $this->newProviderException(
                'OpenAI odgovor ne sadrzi validan JSON payload.',
                $this->buildFailureContext($model, [
                    'http_status' => $response->status(),
                    'body' => $response->body(),
                ])
            );
        }

        $providerTaskId = (string) ($data['id'] ?? '');

        try {
            $textOutput = $this->extractOutputText($data);
        } catch (\Throwable $exception) {
            throw $this->newProviderException(
                $exception->getMessage(),
                $this->buildFailureContext($model, $data, $providerTaskId),
                previous: $exception
            );
        }

        $normalizedPayload = json_decode($textOutput, true);

        if (!is_array($normalizedPayload)) {
            throw $this->newProviderException(
                'OpenAI nije vratio validan JSON za narudzbu.',
                $this->buildFailureContext($model, $data, $providerTaskId)
            );
        }

        return [
            'provider' => 'openai',
            'model' => $model,
            'credits_spent' => $this->calculateCredits((array) Arr::get($data, 'usage', [])),
            'provider_task_id' => $providerTaskId,
            'raw_response' => $data,
            'normalized_payload' => $normalizedPayload,
            'prepared_document' => $preparedDocument,
            'extraction_duration_ms' => $extractionDurationMs,
            'ai_duration_ms' => $aiDurationMs,
        ];
    }

    private function buildFailureContext(string $model, mixed $rawResponse = null, ?string $providerTaskId = null): array
    {
        $context = [
            'provider' => 'openai',
            'model' => $model,
        ];

        if ($rawResponse !== null) {
            $context['raw_response'] = $rawResponse;
        }

        if (trim((string) $providerTaskId) !== '') {
            $context['provider_task_id'] = trim((string) $providerTaskId);
        }

        return $context;
    }

    private function newProviderException(string $message, array $context = [], ?\Throwable $previous = null): RuntimeException
    {
        return new class($message, $context, $previous) extends RuntimeException {
            public function __construct(
                string $message,
                private readonly array $context = [],
                ?\Throwable $previous = null
            ) {
                parent::__construct($message, 0, $previous);
            }

            public function context(): array
            {
                return $this->context;
            }
        };
    }

    private function extractOutputText(array $response): string
    {
        $topLevelText = trim((string) ($response['output_text'] ?? ''));

        if ($topLevelText !== '') {
            return $topLevelText;
        }

        $chunks = [];

        foreach ((array) ($response['output'] ?? []) as $outputItem) {
            foreach ((array) ($outputItem['content'] ?? []) as $contentItem) {
                if (($contentItem['type'] ?? null) === 'output_text') {
                    $chunks[] = (string) ($contentItem['text'] ?? '');
                }
            }
        }

        $outputText = trim(implode("\n", array_filter($chunks, function ($chunk) {
            return trim((string) $chunk) !== '';
        })));

        if ($outputText === '') {
            throw new RuntimeException('OpenAI odgovor ne sadrzi tekstualni izlaz.');
        }

        return $outputText;
    }

    private function buildDocumentInput(OrderAiScan $scan, string $mime, string $bytes, array $preparedDocument): array
    {
        $preparedText = trim((string) ($preparedDocument['provider_input_text'] ?? ''));

        if (trim((string) ($preparedDocument['provider_input_mode'] ?? '')) === 'text' && $preparedText !== '') {
            return [[
                'type' => 'input_text',
                'text' => $preparedText,
            ]];
        }

        if (str_starts_with($mime, 'image/')) {
            return [[
                'type' => 'input_image',
                'image_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
            ]];
        }

        return [[
            'type' => 'input_file',
            'filename' => (string) $scan->source_file_name,
            'file_data' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
        ]];
    }

    private function normalizeDocumentMime(string $mime, string $fileName, string $bytes): string
    {
        $resolved = trim($mime) !== '' ? trim($mime) : 'application/octet-stream';
        $normalized = strtolower($resolved);
        $normalizedName = strtolower(trim($fileName));

        if (
            str_contains($normalized, 'pdf')
            || ($normalizedName !== '' && str_ends_with($normalizedName, '.pdf'))
            || str_starts_with($bytes, '%PDF-')
        ) {
            return 'application/pdf';
        }

        return $resolved;
    }

    private function calculateCredits(array $usage): float
    {
        $totalTokens = (float) ($usage['total_tokens'] ?? 0);

        if ($totalTokens <= 0) {
            return 0.0;
        }

        $creditRate = (float) config('ai-order-scan.credit_rate', 1);

        return round(($totalTokens / 1000) * $creditRate, 4);
    }
}
