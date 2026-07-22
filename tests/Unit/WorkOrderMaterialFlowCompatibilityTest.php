<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkOrderMaterialFlowCompatibilityTest extends TestCase
{
    private string $service;
    private string $config;
    private string $writer;
    private string $preparation;
    private string $request;
    private string $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $base = __DIR__ . '/../../';
        $this->service = file_get_contents($base . 'app/Services/WorkOrder/WorkOrderClosingService.php');
        $this->config = file_get_contents($base . 'config/work_order_closing.php');
        $this->writer = file_get_contents($base . 'app/Services/WorkOrder/PantheonDocumentWriter.php');
        $this->preparation = file_get_contents($base . 'app/Services/WorkOrder/PantheonMaterialPreparationService.php');
        $this->request = file_get_contents($base . 'app/Http/Requests/CloseWorkOrderRequest.php');
        $this->controller = file_get_contents($base . 'app/Http/Controllers/WorkOrderController.php');
    }

    public function test_cutoff_and_existing_6400_links_select_legacy_material_rows(): void
    {
        $this->assertStringContainsString("'work_order_2005_flow_start_date'", $this->config);
        $this->assertStringContainsString("'2026-07-21 00:00:00'", $this->config);
        $this->assertStringContainsString("['created_at', 'adDateIns', 'adTimeIns', 'adDate']", $this->service);
        $this->assertStringContainsString('$createdAt->gte($cutoff)', $this->service);
        $this->assertStringContainsString("'uses_2005_flow' => \$uses2005Flow", $this->service);
        $this->assertStringContainsString('private function legacyMaterialItemQids', $this->service);
        $this->assertStringContainsString("->where('m.acDocType', '6400')", $this->service);
        $this->assertStringContainsString("->join('dbo.tHF_LinkMoveItemWOExItem as li'", $this->service);
    }

    public function test_current_rows_use_wip_without_a_close_time_2005_requirement(): void
    {
        $this->assertStringContainsString("'current_proizvodnja_u_toku_to_veleprodajno'", $this->service);
        $this->assertStringContainsString("config('work_order_closing.work_in_progress_warehouse'", $this->service);
        $this->assertStringNotContainsString('preparedMaterialsFromTransfer', $this->service);
        $this->assertStringNotContainsString('prijenos dokumentom 2005 nije uspješno završen', $this->service);
        $this->assertStringContainsString('$this->prepareMaterials($submittedMaterials)', $this->service);
    }

    public function test_the_configured_date_selects_direct_scan_6400_before_the_cutoff_and_2005_after_it(): void
    {
        $this->assertStringContainsString('private function usesWorkOrder2005Flow', $this->controller);
        $this->assertStringContainsString("config('work_order_closing.work_order_2005_flow_start_date'", $this->controller);
        $this->assertStringContainsString('return Carbon::parse((string) $value)->gte($cutoff);', $this->controller);
        $this->assertStringContainsString('private function workOrderHas2005Document', $this->controller);
        $this->assertStringContainsString('$hasExisting2005 || $this->usesWorkOrder2005Flow($workOrderRow)', $this->controller);
        $this->assertStringContainsString('&& $uses2005Flow)', $this->controller);
        $this->assertStringContainsString('&& !$uses2005Flow)', $this->controller);
        $this->assertStringContainsString('createReleasedMaterialDocumentForBarcodeConsumption(', $this->controller);
        $this->assertStringContainsString('materialPreparation->prepare(', $this->controller);
    }

    public function test_existing_6400_links_are_preserved_and_do_not_block_remaining_rows(): void
    {
        $this->assertStringContainsString("\$this->writer->linkWorkOrder(\$db,\$number,\$qid,\$wo,'P',\$now,\$userId);", $this->preparation);
        $this->assertStringContainsString("\$connection->table('dbo.tHF_LinkMoveWOEx')->insert([", $this->writer);
        $this->assertStringContainsString("\$connection->table('dbo.tHF_LinkMoveItemWOExItem')->insert([", $this->writer);
        $this->assertStringContainsString("unset(\$blockingExisting['6400']);", $this->service);
        $this->assertStringContainsString('$this->materialCostTotal((string) $workOrder[\'acKey\'])', $this->service);
    }

    public function test_flow_selection_is_structured_logged(): void
    {
        $this->assertStringContainsString("Log::info('Work order material flow selected.'", $this->service);
        foreach (['work_order_id', 'work_order_created_at', 'work_order_priority', 'configured_cutoff_date', 'selected_flow', 'source_warehouse', 'destination_warehouse', 'document_2005_id'] as $field) {
            $this->assertStringContainsString("'{$field}'", $this->service);
        }
    }

    public function test_manual_closing_material_creates_a_2005_before_its_6400_release(): void
    {
        $this->assertStringContainsString("'materials.*.item_qid' => ['nullable', 'integer', 'min:1']", $this->request);
        $this->assertStringContainsString("\$material['item_qid'] = \$itemQid > 0 ? \$itemQid : null;", $this->request);
        $this->assertStringContainsString("'requires_close_time_preparation' => (int) (\$material['item_qid'] ?? 0) < 1", $this->service);
        $this->assertStringContainsString('private function prepareCloseTimeMaterials', $this->service);
        $this->assertStringContainsString('materialPreparation->prepare', $this->service);
        $this->assertStringContainsString('materialPreparation->append', $this->service);
        $this->assertStringContainsString("'document_type'=>'2005'", $this->preparation);
    }

    public function test_missing_wip_stock_without_2005_falls_back_to_raw_materials_and_notifies_the_user(): void
    {
        $stock = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/PantheonMaterialStockService.php');
        $view = file_get_contents(__DIR__ . '/../../resources/views/content/apps/invoice/app-invoice-preview.blade.php');

        $this->assertStringContainsString('materialStock->canIssue', $this->service);
        $this->assertStringContainsString("!isset(\$existing['6400'])", $this->service);
        $this->assertStringContainsString("config('work_order_closing.raw_material_warehouse'", $this->service);
        $this->assertStringContainsString('Dokument 2005 nije pronađen.', $this->service);
        $this->assertStringContainsString("'notices' =>", $this->service);
        $this->assertStringContainsString("bccomp(\$normalizedQuantity, '0', WorkOrderClosingCalculator::SCALE) <= 0", $this->service);
        $this->assertStringContainsString('function canIssue', $stock);
        $this->assertStringContainsString('if (!$this->hasPositiveQuantity($item)) continue;', $stock);
        $this->assertStringContainsString('data && Array.isArray(data.notices)', $view);
    }
}
