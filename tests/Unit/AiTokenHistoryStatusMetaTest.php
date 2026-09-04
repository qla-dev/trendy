<?php

namespace Tests\Unit;

use App\Http\Controllers\AiTokenHistoryController;
use App\Models\OrderAiScan;
use ReflectionMethod;
use Tests\TestCase;

class AiTokenHistoryStatusMetaTest extends TestCase
{
    private function resolveStatusMeta(OrderAiScan $scan): array
    {
        $method = new ReflectionMethod(AiTokenHistoryController::class, 'resolveStatusMeta');
        $method->setAccessible(true);

        return $method->invoke(app(AiTokenHistoryController::class), $scan);
    }

    public function test_duplicate_reference_scan_is_not_reported_as_waiting_for_transfer(): void
    {
        $meta = $this->resolveStatusMeta(new OrderAiScan([
            'status' => 'completed',
            'pantheon_transfer_payload' => [
                'transfer_blocked' => true,
                'preview_error_code' => 'duplicate_reference',
                'existing_order_view' => '26-0110-002120',
            ],
        ]));

        $this->assertSame('Već u bazi kao 26-0110-002120', $meta['label']);
        $this->assertSame('warning', $meta['tone']);
    }

    public function test_blocked_scan_without_an_existing_order_number_still_reports_the_block(): void
    {
        $meta = $this->resolveStatusMeta(new OrderAiScan([
            'status' => 'completed',
            'pantheon_transfer_payload' => [
                'transfer_blocked' => true,
                'preview_error_code' => 'duplicate_reference',
            ],
        ]));

        $this->assertSame('Već postoji u bazi', $meta['label']);
        $this->assertSame('warning', $meta['tone']);
    }

    public function test_completed_scan_without_a_block_still_waits_for_transfer(): void
    {
        $meta = $this->resolveStatusMeta(new OrderAiScan([
            'status' => 'completed',
        ]));

        $this->assertSame('Čeka na transfer u bazu', $meta['label']);
        $this->assertSame('info', $meta['tone']);
    }

    public function test_transferred_scan_still_reports_a_successful_transfer(): void
    {
        $meta = $this->resolveStatusMeta(new OrderAiScan([
            'status' => 'completed',
            'pantheon_order_key' => '2601100001719',
            'pantheon_transfer_payload' => [
                'transfer_blocked' => true,
                'preview_error_code' => 'duplicate_reference',
            ],
        ]));

        $this->assertSame('Uspješan transfer', $meta['label']);
        $this->assertSame('success', $meta['tone']);
    }
}
