<?php

namespace Tests\Unit;

use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use Tests\TestCase;

class OrderAiDocumentMetricsTest extends TestCase
{
    public function test_billed_tokens_stay_at_ten_for_documents_up_to_ten_pages(): void
    {
        $service = app(OrderAiDocumentMetrics::class);

        $this->assertSame(10, $service->calculateBilledTokens(1));
        $this->assertSame(10, $service->calculateBilledTokens(10));
    }

    public function test_billed_tokens_grow_one_by_one_after_ten_pages(): void
    {
        $service = app(OrderAiDocumentMetrics::class);

        $this->assertSame(11, $service->calculateBilledTokens(11));
        $this->assertSame(12, $service->calculateBilledTokens(12));
    }
}
