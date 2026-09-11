<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkOrderClosingMakerAssignmentTest extends TestCase
{
    private string $closingService;
    private string $writer;
    private string $preparationService;
    private string $controller;
    private string $closingController;
    private string $view;
    private string $departmentModal;

    protected function setUp(): void
    {
        parent::setUp();
        $base = __DIR__ . '/../../app/Services/WorkOrder/';
        $this->closingService = file_get_contents($base . 'WorkOrderClosingService.php');
        $this->writer = file_get_contents($base . 'PantheonDocumentWriter.php');
        $this->preparationService = file_get_contents($base . 'PantheonMaterialPreparationService.php');
        $this->controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/WorkOrderController.php');
        $this->closingController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/WorkOrderClosingController.php');
        $this->view = file_get_contents(__DIR__ . '/../../resources/views/content/apps/invoice/app-invoice-preview.blade.php');
        $this->departmentModal = file_get_contents(__DIR__ . '/../../resources/views/content/new-components/work-order-department-modal.blade.php');
    }

    public function test_closing_documents_use_the_logged_in_maker_as_responsible_person(): void
    {
        $this->assertStringContainsString('$maker = $this->resolveMaker($userName, $fullName);', $this->closingService);
        $this->assertSame(4, substr_count($this->closingService, "'maker' => \$maker"));
        $this->assertStringContainsString("'maker' => \$this->resolveMaker(\$userName)", $this->closingService);
        $this->assertStringContainsString("'acPrsn3' => \$this->limit(\$maker, 30)", $this->writer);
        $this->assertStringContainsString("'anPrsn3QId' => \$makerQId", $this->writer);
        $this->assertStringContainsString("config('work_order_closing.pantheon_maker_map', [])", $this->closingService);
        $this->assertStringContainsString("'simbad' => 'Simbad Hrnjičić'", file_get_contents(__DIR__ . '/../../config/work_order_closing.php'));
        $this->assertStringContainsString("private function resolveMaker(string \$userName, string \$fullName = '')", $this->closingService);
        $this->assertStringContainsString('$maker = $this->resolveMaker($userName, $fullName);', $this->closingService);
        $this->assertStringContainsString("trim((string) (\$user->username ?? ''))", $this->closingController);
        $this->assertStringContainsString("trim((string) (\$user->name ?? ''))", $this->closingController);
        $this->assertStringContainsString("Pantheon odgovorna osoba nije pronađena", $this->closingService);
    }

    public function test_department_is_empty_by_default_and_copied_when_selected_on_the_work_order(): void
    {
        $this->assertStringContainsString("\$dept = trim((string) (\$context['department'] ?? ''));", $this->writer);
        $this->assertStringContainsString("'acDept' => \$this->limit(\$dept, 30)", $this->writer);
        $this->assertStringContainsString("'anDeptQId' => \$deptQId", $this->writer);
        $this->assertStringContainsString("'department' => \$department", $this->closingService);
        $this->assertStringContainsString("'department_qid' => \$departmentQId", $this->closingService);
        $this->assertStringNotContainsString('resolveDepartment', $this->closingService);
        $this->assertStringNotContainsString('WORK_ORDER_CLOSING_DEPARTMENT', file_get_contents(__DIR__ . '/../../config/work_order_closing.php'));
        $this->assertSame(2, substr_count($this->preparationService, "'acDept'=>trim((string) (\$workOrder['acDept'] ?? ''))"));
    }

    public function test_department_can_be_selected_from_pantheon_and_saved_from_the_work_order_actions(): void
    {
        $this->assertStringContainsString('public function workOrderDepartmentOptions', $this->controller);
        $this->assertStringContainsString('public function updateWorkOrderDepartment', $this->controller);
        $this->assertStringContainsString("'acDept' => \$department", $this->controller);
        $this->assertStringContainsString("\$updates['anDeptQId'] = (int) \$departmentQId", $this->controller);
        $this->assertStringContainsString('id="wo-department-trigger-btn"', $this->view);
        $this->assertTrue(strpos($this->view, 'id="wo-department-trigger-btn"') < strpos($this->view, 'id="wo-protection-trigger-btn"'));
        $this->assertStringContainsString('openDepartmentModal', $this->view);
        $this->assertStringContainsString("\$offset = max(0, (int) \$request->query('offset', 0))", $this->controller);
        $this->assertStringContainsString("'has_more' => \$hasMore", $this->controller);
        $this->assertStringContainsString("'next_offset' => \$offset + \$options->count()", $this->controller);
        $this->assertStringContainsString("'&offset=' + encodeURIComponent(nextOffset)", $this->view);
        $this->assertStringContainsString('results.onscroll', $this->view);
        $this->assertStringContainsString('height: 650px', $this->departmentModal);
        $this->assertStringContainsString('height: 430px', $this->departmentModal);
        $this->assertStringContainsString('overflow-y: auto', $this->departmentModal);
    }
}
