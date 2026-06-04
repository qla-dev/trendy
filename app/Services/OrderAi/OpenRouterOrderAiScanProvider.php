<?php

namespace App\Services\OrderAi;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use App\Services\OrderAi\Support\OrderAiResponseSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenRouterOrderAiScanProvider implements OrderAiScanProvider
{
    public function supportsLiveTransfer(): bool
    {
        return true;
    }

    public function scan(OrderAiScan $scan): array
    {
        $apiKey = trim((string) config('ai-order-scan.openrouter.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('OPENROUTER_API_KEY nije postavljen.');
        }

        $disk = (string) config('ai-order-scan.storage_disk', 'local');

        if (!Storage::disk($disk)->exists($scan->source_file_path)) {
            throw new RuntimeException('Uploadovani dokument nije pronadjen na disku.');
        }

        $bytes = Storage::disk($disk)->get($scan->source_file_path);
        $mime = trim((string) ($scan->source_mime_type ?: 'application/octet-stream'));
        $model = trim((string) config('ai-order-scan.model', 'openai/gpt-4.1-mini'));
        $baseUrl = rtrim((string) config('ai-order-scan.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $prompt = trim((string) ($scan->request_prompt ?: config('ai-order-scan.prompt')));

        $client = Http::withToken($apiKey)
            ->timeout((int) config('ai-order-scan.timeout', 120))
            ->acceptJson()
            ->asJson();

        $headers = array_filter([
            'HTTP-Referer' => trim((string) config('ai-order-scan.openrouter.http_referer')),
            'X-Title' => trim((string) config('ai-order-scan.openrouter.title')),
        ], function ($value) {
            return $value !== '';
        });

        if ($headers !== []) {
            $client = $client->withHeaders($headers);
        }

        $response = $client->post($baseUrl . '/chat/completions', [
            'model' => $model,
            'temperature' => 0,
            'provider' => [
                'require_parameters' => true,
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'trendy_order_scan',
                    'strict' => true,
                    'schema' => OrderAiResponseSchema::definition(),
                ],
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Extract the order from this document and return JSON that matches the provided schema.',
                        ],
                        $this->buildFileContent($scan, $mime, $bytes),
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('OpenRouter odgovor nije uspjesan: ' . $response->body());
        }

        $data = $response->json();
        $textOutput = $this->extractOutputText($data);
        $normalizedPayload = json_decode($textOutput, true);

        if (!is_array($normalizedPayload)) {
            throw new RuntimeException('OpenRouter nije vratio validan JSON za narudzbu.');
        }

        return [
            'provider' => 'openrouter',
            'model' => $model,
            'credits_spent' => $this->calculateCredits((array) Arr::get($data, 'usage', [])),
            'provider_task_id' => (string) ($data['id'] ?? ''),
            'raw_response' => $data,
            'normalized_payload' => $normalizedPayload,
        ];
    }

    private function buildFileContent(OrderAiScan $scan, string $mime, string $bytes): array
    {
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($bytes);

        if (str_starts_with($mime, 'image/')) {
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUri,
                ],
            ];
        }

        return [
            'type' => 'file',
            'file' => [
                'filename' => (string) ($scan->source_file_name ?: 'document'),
                'file_data' => $dataUri,
            ],
        ];
    }

    private function extractOutputText(array $response): string
    {
        $content = Arr::get($response, 'choices.0.message.content');

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        if (is_array($content)) {
            $chunks = [];

            foreach ($content as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $chunks[] = trim($item);
                    continue;
                }

                if (!is_array($item)) {
                    continue;
                }

                $text = trim((string) ($item['text'] ?? ''));

                if ($text !== '') {
                    $chunks[] = $text;
                }
            }

            $output = trim(implode("\n", $chunks));

            if ($output !== '') {
                return $output;
            }
        }

        throw new RuntimeException('OpenRouter odgovor ne sadrzi tekstualni JSON izlaz.');
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
