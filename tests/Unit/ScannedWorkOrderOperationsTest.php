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

    public function test_scanner_routes_checkpoint_roles_to_the_operations_view(): void
    {
        $scanner = file_get_contents(resource_path('views/content/new-components/nalog-scan.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/WorkOrderController.php'));
        $view = file_get_contents(resource_path('views/content/apps/invoice/app-invoice-scan-operations.blade.php'));
        $preview = file_get_contents(resource_path('views/content/apps/invoice/app-invoice-preview.blade.php'));

        $this->assertStringContainsString("var checkpointUrl = workOrder.checkpoint_url || '';", $scanner);
        $this->assertStringContainsString("if (!checkpointUrl)", $scanner);
        $this->assertStringContainsString("route('app-invoice-scan-operations'", $controller);
        $this->assertStringContainsString("operationMatchesScanRole(\$selectedOperation, \$role)", $controller);
        $this->assertStringContainsString("scan-operation-row {{ \$finished ? 'is-finished' : (\$enabled ? 'is-enabled' : 'is-disabled') }}", $view);
        $this->assertStringContainsString("'operation_id' => ['required', 'string', 'max:100']", $controller);
        $this->assertStringContainsString("'acIssueFinished' => \$finished ? 'Y' : 'N'", $controller);
        $this->assertStringContainsString("sortBy(fn (array \$operation): int => (\$operation['is_role_operation'] ?? false) ? 0 : 1)", $controller);
        $this->assertStringContainsString('calc(4rem + env(safe-area-inset-bottom, 0px))', $view);
        $this->assertStringContainsString('-webkit-appearance: none', $view);
        $this->assertStringContainsString('wo-operation-finished-row', $preview);
        $this->assertStringContainsString("content: '\\2713'", $preview);
        $this->assertStringContainsString('markWorkOrderOperationFinished', $controller);
        $this->assertStringContainsString('usort($workOrderRegOperations', $controller);
        $this->assertStringNotContainsString('scan-operation-state', $view);
        $this->assertStringNotContainsString('fa-lock', $view);
    }

    public function test_checkpoint_operations_are_added_on_scan_and_excluded_from_closing_time(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/WorkOrderController.php'));
        $closing = file_get_contents(app_path('Services/WorkOrder/WorkOrderClosingService.php'));
        $preview = file_get_contents(resource_path('views/content/apps/invoice/app-invoice-preview.blade.php'));

        $this->assertStringContainsString("'bravarija' => ['code' => 'OP50', 'name' => 'Operacija - Bravarija']", $controller);
        $this->assertStringContainsString("'kontrola' => ['code' => 'OP60', 'name' => 'Operacija - Kontrola']", $controller);
        $this->assertStringContainsString('ensureScanCheckpointOperations($workOrderKey', $controller);
        $this->assertStringContainsString("'acOperationType' => 'D'", $controller);
        $this->assertStringContainsString('isScanCheckpointOperation($operationCode', $closing);
        $this->assertStringContainsString('isScanCheckpointOperation((string) ($input[\'code\'] ?? \'\'))', $closing);
        $this->assertStringContainsString('Its completion is owned by the Bravarija/Kontrola scan', $closing);
        $this->assertStringContainsString("!in_array(\$code, ['OP50', 'OP60'], true)", $preview);
    }
}
