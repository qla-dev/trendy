<?php

namespace Tests\Unit;

use App\Services\WorkOrder\PantheonDocumentNumberGenerator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

class PantheonDocumentNumberGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @dataProvider documentTypes */
    public function test_numbering_uses_year_document_type_and_locked_next_sequence(string $type, string $last, string $expected): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(function (string $sql, array $bindings) use ($type) {
                return str_contains($sql, 'UPDLOCK, HOLDLOCK') && $bindings === [$type, '26' . $type . '%'];
            })
            ->andReturn((object) ['acKey' => $last]);

        $result = (new PantheonDocumentNumberGenerator())->next($connection, $type, Carbon::parse('2026-07-15'));

        $this->assertSame($expected, $result['number']);
    }

    public function documentTypes(): array
    {
        return [
            ['6600', '2666000000123', '26-6600-000124'],
            ['6100', '2661000000456', '26-6100-000457'],
        ];
    }
}
