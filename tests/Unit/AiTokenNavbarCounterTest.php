<?php

namespace Tests\Unit;

use App\Models\OrderAiScan;
use App\Support\AiTokenNavbarCounter;
use Tests\TestCase;

class AiTokenNavbarCounterTest extends TestCase
{
    public function test_it_uses_the_same_billable_transfer_rules_as_ai_token_history(): void
    {
        $counter = app(AiTokenNavbarCounter::class);

        $this->assertSame(10, $counter->billedTokensFor(new OrderAiScan([
            'status' => 'transferred',
            'page_count' => 2,
        ])));
        $this->assertSame(12, $counter->billedTokensFor(new OrderAiScan([
            'status' => 'completed',
            'page_count' => 12,
            'pantheon_order_key' => '2601100001719',
        ])));
        $this->assertSame(0, $counter->billedTokensFor(new OrderAiScan([
            'status' => 'completed',
            'page_count' => 12,
        ])));
    }
}
