<?php

namespace Tests\Unit;

use App\Http\Controllers\WorkOrderController;
use App\Support\AiScanFailureMessage;
use App\Services\WorkOrder\ExcessStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ScanCreateFailureTest extends TestCase
{
    public function test_scan_create_response_excludes_debug_details_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException(
            'SQLSTATE[23000]: FOREIGN KEY constraint "rtHF_WOEx_tHE_SetItem_3". SQL: insert into dbo.tHF_WOEx'
        ));

        $response = (new WorkOrderController())->createFromScan(
            Request::create('/scan', 'POST', ['identifier' => '2601100002561;2;DN703554']),
            Mockery::mock(ExcessStockService::class)
        );

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame(['message'], array_keys($data));
        $this->assertFalse(AiScanFailureMessage::isTechnical($data['message']));
        $this->assertStringContainsString('šifra artikla', $data['message']);
    }

    public function test_scan_insert_preserves_pantheon_foreign_keys_with_leading_spaces(): void
    {
        $controller = new WorkOrderController();
        foreach (['tableNonInsertableColumnsCache', 'tableStringLengthMapCache'] as $name) {
            $property = new ReflectionProperty($controller, $name);
            $property->setAccessible(true);
            $property->setValue($controller, []);
        }

        $columns = Mockery::mock();
        $columns->shouldReceive('where')->andReturnSelf();
        $columns->shouldReceive('pluck')->with('COLUMN_NAME')->andReturn(collect(['acIdent', 'acCostDrv', 'acName']));
        DB::shouldReceive('table')->with('INFORMATION_SCHEMA.COLUMNS')->andReturn($columns);
        $priorities = Mockery::mock();
        $priorities->shouldReceive('where', 'orderBy')->andReturnSelf();
        $priorities->shouldReceive('get')->andReturn(collect());
        DB::shouldReceive('table')->with(config('workorders.schema', 'dbo') . '.tHE_SetDeliveryPriority')->andReturn($priorities);

        $method = new ReflectionMethod($controller, 'buildWorkOrderInsertPayloadFromOrder');
        $method->setAccessible(true);
        $payload = $method->invoke($controller, [], [
            'product_code' => 'DN703554',
            'product_name' => 'COVER ATTACHMENT PLATE',
            'catalog' => ['acIdent' => ' DN703554'],
            'protection' => ['code' => ' galvanisch verz'],
        ], [], null, 36.0);

        $this->assertSame(' DN703554', $payload['acIdent']);
        $this->assertSame(' galvanisch verz', $payload['acCostDrv']);
    }

    public function test_scan_create_failures_have_actionable_messages_without_technical_details(): void
    {
        foreach (['rtHF_WOEx_tHE_SetItem_3', 'rtHF_WOEx_tHE_CostDrv_7', 'other'] as $constraint) {
            $message = AiScanFailureMessage::workOrderCreate('SQLSTATE[23000]: The INSERT statement conflicted with the FOREIGN KEY constraint "' . $constraint . '". SQL: insert into dbo.tHF_WOEx');
            $this->assertFalse(AiScanFailureMessage::isTechnical($message));
            $this->assertStringContainsString('administratora', $message);
        }

        $this->assertSame(
            'Radni nalog trenutno nije moguće kreirati. Pokušajte ponovo ili kontaktirajte administratora.',
            AiScanFailureMessage::workOrderCreate('Internal server path /home/example/vendor/test.php')
        );
    }
}
