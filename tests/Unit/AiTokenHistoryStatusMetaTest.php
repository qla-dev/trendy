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

    private function invokeColumns(string $method, array $arguments = []): array
    {
        $reflection = new ReflectionMethod(AiTokenHistoryController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(app(AiTokenHistoryController::class), $arguments);
    }

    public function test_polling_reads_every_column_the_status_badge_depends_on(): void
    {
        $statusColumns = $this->invokeColumns('historyStatusColumns');

        // Without these the polled row falls back to "Čeka na transfer u bazu"
        // and overwrites the label the page rendered.
        $this->assertContains('pantheon_transfer_payload', $statusColumns);
        $this->assertContains('normalized_payload', $statusColumns);
        $this->assertContains('pantheon_order_key', $statusColumns);
        $this->assertContains('status', $statusColumns);
    }

    public function test_polled_rows_read_the_same_columns_as_the_rendered_page(): void
    {
        $this->assertEmpty(array_diff(
            $this->invokeColumns('historyStatusColumns'),
            $this->invokeColumns('historyListColumns', [false])
        ));
    }

    public function test_a_polled_duplicate_reference_row_keeps_the_blocked_label(): void
    {
        $method = new ReflectionMethod(AiTokenHistoryController::class, 'mapHistoryStatusRow');
        $method->setAccessible(true);

        $row = $method->invoke(app(AiTokenHistoryController::class), new OrderAiScan([
            'id' => 492,
            'status' => 'completed',
            'pantheon_transfer_payload' => [
                'transfer_blocked' => true,
                'preview_error_code' => 'duplicate_reference',
                'existing_order_view' => '26-0110-002120',
            ],
        ]));

        $this->assertSame('Već u bazi kao 26-0110-002120', $row['status_label']);
        $this->assertSame('warning', $row['status_tone']);
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
