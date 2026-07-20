<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkOrderClosingFlowStructureTest extends TestCase
{
    private string $view;
    private string $service;
    private string $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = file_get_contents(__DIR__ . '/../../resources/views/content/apps/invoice/app-invoice-preview.blade.php');
        $this->service = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/WorkOrderClosingService.php');
        $this->writer = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/PantheonDocumentWriter.php');
    }

    public function test_close_action_is_inside_other_options_and_directly_above_status(): void
    {
        $collapse = strpos($this->view, 'id="wo-other-options-collapse"');
        $close = strpos($this->view, 'id="wo-close-order-btn"');
        $status = strpos($this->view, 'id="wo-status-trigger-btn"');

        $this->assertNotFalse($collapse);
        $this->assertTrue($collapse < $close && $close < $status);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $this->view);
        $this->assertStringContainsString('.wo-other-options-collapse.collapse:not(.show)', $this->view);
    }

    public function test_modal_has_operations_materials_worker_and_time_fields(): void
    {
        $this->assertStringContainsString('id="close-work-order-modal"', $this->view);
        $this->assertStringContainsString('data-bs-target="#close-work-order-operations"', $this->view);
        $this->assertStringContainsString('data-bs-target="#close-work-order-materials"', $this->view);
        $this->assertStringContainsString('wo-close-worker', $this->view);
        $this->assertStringContainsString('wo-close-worker-search', $this->view);
        $this->assertStringContainsString('wo-close-time', $this->view);
        $this->assertStringContainsString('wo-close-start-time', $this->view);
        $this->assertStringContainsString('wo-close-end-time', $this->view);
        $this->assertStringContainsString('wo-close-start-hour', $this->view);
        $this->assertStringContainsString('wo-close-start-minute', $this->view);
        $this->assertStringContainsString('wo-close-end-hour', $this->view);
        $this->assertStringContainsString('wo-close-end-minute', $this->view);
        $this->assertStringContainsString('Početak izrade', $this->view);
        $this->assertStringContainsString('Kraj izrade', $this->view);
        $this->assertStringContainsString('Trajanje (min/jedinica)', $this->view);
        $this->assertStringContainsString('>Prijem</button>', $this->view);
        $this->assertStringContainsString('close-work-order-receipts-table', $this->view);
        $this->assertStringContainsString('wo-close-add-scrap-receipt-btn', $this->view);
        $this->assertStringContainsString('wo-close-copy-row-btn', $this->view);
        $this->assertStringContainsString('wo-close-clear-row-btn', $this->view);
        $this->assertStringContainsString('Očisti red', $this->view);
        $this->assertStringContainsString('class="wo-close-copy-row-btn"', $this->view);
        $this->assertStringNotContainsString('class="btn btn-outline-primary btn-sm wo-close-copy-row-btn"', $this->view);
        $this->assertStringNotContainsString('class="btn btn-sm wo-close-copy-row-btn"', $this->view);
        $this->assertStringContainsString('wo-close-delete-row-btn', $this->view);
        $this->assertStringContainsString('searchPantheonWorkers', $this->view);
        $this->assertStringContainsString('positionWorkerSuggestions', $this->view);
        $this->assertStringContainsString('highlightWorkerSuggestion', $this->view);
        $this->assertStringContainsString('moveToNextClosingFieldInRow', $this->view);
        $this->assertStringContainsString('ensureOp30Rows', $this->view);
        $this->assertStringContainsString('workOrderBreaks', $this->view);
        $this->assertStringContainsString('readClockFieldValue', $this->view);
        $this->assertStringContainsString('setClockFieldValue', $this->view);
        $this->assertStringContainsString('type="text" inputmode="numeric" maxlength="2" placeholder="HH"', $this->view);
        $this->assertStringContainsString('type="text" inputmode="numeric" maxlength="2" placeholder="MM"', $this->view);
        $this->assertStringNotContainsString('wo-close-duration-icon', $this->view);
        $this->assertStringContainsString('copyButton.blur()', $this->view);
        $this->assertStringContainsString('closingFocusableFields', $this->view);
        $this->assertStringContainsString("['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown']", $this->view);
        $this->assertStringContainsString('Up/down preserves the active column', $this->view);
        $this->assertStringContainsString('resetCopyButtonVisualState', $this->view);
        $this->assertStringContainsString('.wo-close-copy-row-btn:hover:not(:active)', $this->view);
        $this->assertStringContainsString("button.style.removeProperty(property)", $this->view);
        $this->assertStringNotContainsString('setCopyButtonHoverState', $this->view);
        $this->assertStringNotContainsString('copyHoverSuppressed', $this->view);
        $this->assertStringContainsString('endMinutes >= startMinutes', $this->view);
        $this->assertStringContainsString('normalizeClockFieldsOnBlur', $this->view);
        $this->assertStringContainsString('Math.min(Number(rawValue), maximum)', $this->view);
        $this->assertStringContainsString('Ovo je vrijeme tokom pauze.', $this->view);
        $this->assertStringContainsString('operationTimeHasBreak', $this->view);
        $this->assertStringContainsString('setClockBreakState', $this->view);
        $this->assertStringContainsString('isBreakTime', $this->view);
        $this->assertStringContainsString('wo-close-clock-break', $this->view);
        $this->assertStringContainsString('wo-close-time-error', $this->view);
        $this->assertStringContainsString('minutes > workBreak[0] && minutes < workBreak[1]', $this->view);
        $this->assertStringContainsString('missingClosingFields', $this->view);
        $this->assertStringContainsString('Nedostaju obavezna polja', $this->view);
        $this->assertStringContainsString('if (isClosingOperationRowEmpty(row))', $this->view);
        $this->assertStringContainsString('[operation.worker_id, operation.time, operation.start_time, operation.end_time]', $this->view);
        $this->assertStringContainsString('modal-dialog-centered', $this->view);
    }

    public function test_closing_is_transactional_duplicate_protected_and_updates_closed_status(): void
    {
        $this->assertStringContainsString('$this->connection->transaction(', $this->service);
        $this->assertStringContainsString('existingClosingDocuments', $this->service);
        $this->assertStringContainsString("whereIn('m.acDocType', ['6100', '6400', '6600', '7100'])", $this->service);
        $this->assertStringContainsString('preparedMaterialsFromTransfer', $this->service);
        $this->assertStringContainsString('materialStock->issue', $this->service);
        $this->assertStringContainsString("'acStatusMF' => 'Z'", $this->service);
        $this->assertStringContainsString("'acReceiveFinished' => 'Y'", $this->service);
        $this->assertStringContainsString("'acStatusMF' => 'R'", $this->service);
        $this->assertStringContainsString('isBreakMinute', $this->service);
        $this->assertStringContainsString('Početno ili završno vrijeme je tokom pauze.', $this->service);
        $this->assertStringContainsString('$minutes > $start && $minutes < $end', $this->service);
        $this->assertStringContainsString("\$operationCode === 'OP30' && \$this->isEmptyOperationInput(\$input)", $this->service);
        $this->assertStringContainsString('private function isEmptyOperationInput', $this->service);
        $this->assertStringContainsString("'minutes' => \$minutes", $this->service);
        $receipt = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/PantheonFinishedGoodsReceiptService.php');
        $stock = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/PantheonFinishedGoodsStockService.php');
        $this->assertStringContainsString('$this->stock->receive(', $receipt);
        $this->assertStringContainsString('dbo.tHE_Stock WITH (UPDLOCK, HOLDLOCK)', $stock);
        $this->assertStringContainsString('existingDecimal', $stock);
        $this->assertStringContainsString('$submittedByItem[$qid][] = $operation', $this->service);
        $this->assertStringContainsString("'worker_entries' => \$workerEntries", $this->service);
        $this->assertStringContainsString('Završno vrijeme ne može biti prije početnog vremena.', $this->service);
    }

    public function test_closed_work_order_uses_pantheon_z_code_and_dropdown_label(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/WorkOrderController.php');
        $statusModal = file_get_contents(__DIR__ . '/../../resources/views/content/new-components/change-status-modal.blade.php');

        $this->assertStringContainsString("'acStatusMF' => 'Z'", $this->service);
        $this->assertStringContainsString("'Z' => ['label' => \"Zaklju\\u{010D}en\"", $controller);
        $this->assertStringContainsString("'R' => ['label' => \"Djelomi\\u{010D}no zaklju\\u{010D}en\"", $controller);
        $this->assertStringContainsString("'zatvoren' => 'zakljucen'", $statusModal);
        $this->assertStringContainsString("'Zaključen'", $statusModal);
    }

    public function test_confirmed_date_sources_and_link_types_are_present(): void
    {
        $this->assertStringContainsString("\$workOrder['adLnkDate']", $this->writer);
        $this->assertStringContainsString("\$workOrder['adDate']", $this->writer);
        $this->assertStringContainsString("'adDate' => \$documentDate", $this->writer);
        $this->assertStringContainsString("'adDateDoc1' => \$orderDate", $this->writer);
        $this->assertStringContainsString("'adDateDoc2' => \$workOrderDate", $this->writer);
        $this->assertStringContainsString("(string) \$number['type'] === '6100'", $this->writer);
        $operation = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/PantheonOperationDocumentService.php');
        $receipt = file_get_contents(__DIR__ . '/../../app/Services/WorkOrder/PantheonFinishedGoodsReceiptService.php');
        $this->assertStringContainsString("\$workOrder['acKey'], 'P'", $operation);
        $this->assertStringContainsString("\$workOrder['acKey'], 'M'", $receipt);
        $this->assertStringContainsString("'acUMConverted' => 'MIN'", $operation);
    }

    public function test_material_quantity_editor_uses_the_existing_consumption_update_flow(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/WorkOrderController.php');

        $this->assertStringContainsString('wo-material-quantity-input', $this->view);
        $this->assertStringContainsString('wo-material-save-quantity-btn', $this->view);
        $this->assertStringContainsString('id="close-work-order-materials-table"', $this->view);
        $this->assertStringContainsString('function saveMaterialQuantity', $this->view);
        $this->assertStringContainsString('savePendingClosingMaterialQuantities', $this->view);
        $this->assertStringContainsString('materialsSavedForClose', $this->view);
        $this->assertStringContainsString('mutationConfig.plannedConsumptionUpdateUrl', $this->view);
        $this->assertStringContainsString("'i.anQId as __item_qid'", $controller);
        $this->assertStringContainsString("'item_qid' => \$this->value(\$row, ['__item_qid'", $controller);
        $this->assertStringContainsString('public function updatePlannedConsumptionItem', $controller);
        $this->assertStringContainsString("['anPlanQty']", $controller);
        $this->assertStringContainsString("['anQty', 'anQty1']", $controller);
    }
}
