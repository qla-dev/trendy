<?php

namespace Tests\Feature;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PantheonWorkOrderDocumentLinksTest extends TestCase
{
    private ConnectionInterface $pantheon;

    protected function setUp(): void
    {
        parent::setUp();

        $connectionName = (string) config('services.work_order.target_connection');
        $database = (string) config('database.connections.' . $connectionName . '.database');
        if (strtoupper($database) !== 'BA_TRENDY_TESTNA') {
            $this->markTestSkipped('Pantheon link-report test is hard-locked to BA_TRENDY_TESTNA.');
        }

        $this->pantheon = DB::connection($connectionName);
    }

    /**
     * Read-only report and regression check for the links Pantheon uses in
     * Povezani dokumenti. Run with:
     * php artisan test --filter=PantheonWorkOrderDocumentLinksTest
     */
    public function test_related_document_link_values_are_present_and_reported(): void
    {
        $expected = [
            '26-6000-000001' => [
                '26-6400-000714' => ['6400', 'P'],
                '26-6100-000714' => ['6100', 'M'],
                '26-6600-000712' => ['6600', 'P'],
            ],
            '26-6000-003136' => [
                '26-6400-003924' => ['6400', 'P'],
            ],
            '26-6000-003620' => [
                '26-6400-003954' => ['6400', 'P'],
                '26-6400-003955' => ['6400', 'P'],
                '26-6400-003957' => ['6400', 'P'],
                '26-6100-003816' => ['6100', 'M'],
                '26-6600-003787' => ['6600', 'P'],
                '26-6600-003788' => ['6600', 'P'],
            ],
        ];

        $workOrderNumbers = array_keys($expected);
        $links = $this->pantheon->table('dbo.tHF_WOEx as wo')
            ->join('dbo.tHF_LinkMoveWOEx as l', 'l.acLnkKey', '=', 'wo.acKey')
            ->join('dbo.tHE_Move as m', 'm.acKey', '=', 'l.acKey')
            ->whereIn('wo.acKeyView', $workOrderNumbers)
            ->whereIn('m.acDocType', ['6400', '6100', '6600'])
            ->orderBy('wo.acKeyView')
            ->orderBy('m.acDocType')
            ->orderBy('m.acKeyView')
            ->get([
                'wo.acKeyView as work_order_number',
                'm.acKeyView as document_number',
                'm.acDocType as document_type',
                'm.acKey as document_key',
                'l.acLnkKey as linked_work_order_key',
                'l.acType as link_type',
                'l.anMoveQId as move_qid',
            ]);

        $report = $links->map(fn ($link) => [
            'work_order' => trim((string) $link->work_order_number),
            'document' => trim((string) $link->document_number),
            'document_type' => trim((string) $link->document_type),
            'document_key' => trim((string) $link->document_key),
            'linked_work_order_key' => trim((string) $link->linked_work_order_key),
            'link_type' => trim((string) $link->link_type),
            'move_qid' => (int) $link->move_qid,
        ])->all();

        fwrite(STDOUT, PHP_EOL . 'Pantheon WO document-link report:' . PHP_EOL
            . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);

        foreach ($expected as $workOrderNumber => $documents) {
            foreach ($documents as $documentNumber => [$documentType, $linkType]) {
                $link = $links->first(function ($candidate) use ($workOrderNumber, $documentNumber) {
                    return trim((string) $candidate->work_order_number) === $workOrderNumber
                        && trim((string) $candidate->document_number) === $documentNumber;
                });

                $this->assertNotNull($link, "Missing Pantheon WO link: {$workOrderNumber} ← {$documentNumber}");
                $this->assertSame($documentType, trim((string) $link->document_type));
                $this->assertSame($linkType, trim((string) $link->link_type));
                $this->assertGreaterThan(0, (int) $link->move_qid);
                $this->assertNotSame('', trim((string) $link->linked_work_order_key));
            }
        }
    }
}
