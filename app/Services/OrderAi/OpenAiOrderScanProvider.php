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
        $apiKey = trim((string) config('ai-order-scan.openai.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY nije postavljen.');
        }

        $disk = (string) config('ai-order-scan.storage_disk', 'local');

        if (!Storage::disk($disk)->exists($scan->source_file_path)) {
            throw new RuntimeException('Učitani dokument nije pronađen na disku.');
        }

        $bytes = Storage::disk($disk)->get($scan->source_file_path);
        $mime = trim((string) ($scan->source_mime_type ?: 'application/octet-stream'));
        $model = trim((string) config('ai-order-scan.model', 'gpt-5'));
        $baseUrl = rtrim((string) config('ai-order-scan.openai.base_url', 'https://api.openai.com/v1'), '/');
        $prompt = trim((string) ($scan->request_prompt ?: config('ai-order-scan.prompt')));
        $preparedDocument = app(OrderAiDocumentPreparationService::class)->prepareDocument(
            (string) ($scan->document_profile ?? ''),
            (string) ($scan->source_file_name ?? 'document'),
            $mime,
            $bytes
        );
        $documentInput = $this->buildDocumentInput($scan, $mime, $bytes, $preparedDocument);

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

        if (!$response->successful()) {
            throw new RuntimeException('OpenAI odgovor nije uspješan: ' . $response->body());
        }

        $data = $response->json();
        $textOutput = $this->extractOutputText($data);
        $normalizedPayload = json_decode($textOutput, true);

        if (!is_array($normalizedPayload)) {
            throw new RuntimeException('OpenAI nije vratio validan JSON za narudžbu.');
        }

        return [
            'provider' => 'openai',
            'model' => $model,
            'credits_spent' => $this->calculateCredits((array) Arr::get($data, 'usage', [])),
            'provider_task_id' => (string) ($data['id'] ?? ''),
            'raw_response' => $data,
            'normalized_payload' => $normalizedPayload,
        ];
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
            throw new RuntimeException('OpenAI odgovor ne sadrži tekstualni izlaz.');
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
