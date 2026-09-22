<?php

namespace Tests\Unit;

use App\Http\Controllers\WorkOrderController;
use ReflectionMethod;
use Tests\TestCase;

class ScannedWorkOrderOperationsTest extends TestCase
{
    public function test_each_scanner_role_only_matches_its_own_operation(): void
    {
        $controller = new WorkOrderController();
        $matchesRole = new ReflectionMethod($controller, 'operationMatchesScanRole');
        $matchesRole->setAccessible(true);

        $kontrola = ['operacija' => 'OP160', 'naziv' => 'Završna kontrola'];
        $bravarija = ['operacija' => 'OP150', 'naziv' => 'Bravarski radovi'];
        $other = ['operacija' => 'OP030', 'naziv' => 'Glodanje'];

        $this->assertTrue($matchesRole->invoke($controller, $kontrola, 'kontrola'));
        $this->assertFalse($matchesRole->invoke($controller, $bravarija, 'kontrola'));
        $this->assertTrue($matchesRole->invoke($controller, $bravarija, 'bravarija'));
        $this->assertFalse($matchesRole->invoke($controller, $kontrola, 'bravarija'));
        $this->assertFalse($matchesRole->invoke($controller, $other, 'kontrola'));
        $this->assertFalse($matchesRole->invoke($controller, $other, 'bravarija'));
    }

    public function test_scanner_routes_checkpoint_roles_to_the_regular_details_operations_tab(): void
    {
        $scanner = file_get_contents(resource_path('views/content/new-components/nalog-scan.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/WorkOrderController.php'));
        $preview = file_get_contents(resource_path('views/content/apps/invoice/app-invoice-preview.blade.php'));

        $this->assertStringContainsString("var checkpointUrl = workOrder.checkpoint_url || '';", $scanner);
        $this->assertStringContainsString("if (!checkpointUrl)", $scanner);
        $this->assertStringContainsString("'checkpoint_url' => null", $controller);
        $this->assertStringContainsString('ensureScanCheckpointOperations(', $controller);
        $this->assertStringContainsString("operationMatchesScanRole(\$selectedOperation, \$role)", $controller);
        $this->assertStringContainsString("'operation_id' => ['required', 'string', 'max:100']", $controller);
        $this->assertStringContainsString("'acIssueFinished' => \$finished ? 'Y' : 'N'", $controller);
        $this->assertStringContainsString("'operationCheckpointUrl' => \$scanRole !== null", $controller);
        $this->assertStringContainsString('data-checkpoint-role', $preview);
        $this->assertStringContainsString('wo-operation-role-locked-row', $preview);
        $this->assertStringContainsString('wo-mobile-column-label', $preview);
        $this->assertStringContainsString('Alt.</span>', $preview);
        $this->assertStringContainsString('Pos.</span>', $preview);
        $this->assertStringContainsString('wo-operation-finished-row', $preview);
        $this->assertStringContainsString("content: '\\2713'", $preview);
        $this->assertStringContainsString('markWorkOrderOperationFinished', $controller);
        $this->assertStringContainsString('usort($workOrderRegOperations', $controller);
    }

    public function test_scans_add_only_the_scanning_departments_checkpoint_and_exclude_it_from_closing_time(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/WorkOrderController.php'));
        $closing = file_get_contents(app_path('Services/WorkOrder/WorkOrderClosingService.php'));
        $preview = file_get_contents(resource_path('views/content/apps/invoice/app-invoice-preview.blade.php'));

        $this->assertStringContainsString("'bravarija' => ['code' => 'OP50', 'name' => 'Operacija - Bravarija']", $controller);
        $this->assertStringContainsString("'kontrola' => ['code' => 'OP60', 'name' => 'Operacija - Kontrola']", $controller);
        $this->assertStringContainsString('ensureScanCheckpointOperations($workOrderKey, (int) ($request->user()->id ?? 0), $role)', $controller);
        $this->assertStringContainsString('[$role => self::SCAN_CHECKPOINT_OPERATIONS[$role]]', $controller);
        $this->assertStringContainsString("'acOperationType' => 'D'", $controller);
        $this->assertStringContainsString('isScanCheckpointOperation($operationCode', $closing);
        $this->assertStringContainsString('isScanCheckpointOperation((string) ($input[\'code\'] ?? \'\'))', $closing);
        $this->assertStringContainsString('Its completion is owned by the Bravarija/Kontrola scan', $closing);
        $this->assertStringContainsString("!in_array(\$code, ['OP50', 'OP60'], true)", $preview);
    }
}
