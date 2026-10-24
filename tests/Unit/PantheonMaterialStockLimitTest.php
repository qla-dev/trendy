<?php

namespace Tests\Unit;

use App\Services\WorkOrder\PantheonMaterialStockService;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class PantheonMaterialStockLimitTest extends TestCase
{
    public function test_excess_issue_uses_only_available_stock_and_reports_the_remainder(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->exactly(2))->method('selectOne')
            ->willReturnCallback(static function (string $sql, array $bindings) {
                self::assertStringContainsString('UPDLOCK, HOLDLOCK', $sql);
                return $bindings[1] === 'STEEL' ? (object) ['anStock' => '2.000000'] : null;
            });

        $result = (new PantheonMaterialStockService())->limitIssueToAvailable($db, 'Dodatne sirovine', [
            ['code' => 'STEEL', 'name' => 'Čelik', 'unit' => 'KG', 'quantity' => '3.000000', 'price' => '4.000000', 'total' => '12.000000'],
            ['code' => 'STEEL', 'name' => 'Čelik', 'unit' => 'KG', 'quantity' => '1.000000', 'price' => '4.000000', 'total' => '4.000000'],
            ['code' => 'MISSING', 'name' => 'Bez zalihe', 'unit' => 'KG', 'quantity' => '5.000000', 'price' => '2.000000', 'total' => '10.000000'],
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('2.000000', $result['items'][0]['quantity']);
        $this->assertSame('8.000000', $result['items'][0]['total']);
        $this->assertSame(['2.000000', '0.000000', '0'], array_column($result['shortages'], 'issued'));
        $this->assertSame(['1.000000', '1.000000', '5.000000'], array_column($result['shortages'], 'missing'));
    }
}
