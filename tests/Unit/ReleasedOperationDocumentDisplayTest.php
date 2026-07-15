<?php

namespace Tests\Unit;

use App\Http\Controllers\ReleasedMaterialDocumentController;
use App\Http\Controllers\ReleasedOperationDocumentController;
use App\Providers\MenuServiceProvider;
use ReflectionClass;
use Tests\TestCase;

class ReleasedOperationDocumentDisplayTest extends TestCase
{
    public function test_operation_rn_price_is_rate_multiplied_by_quantity(): void
    {
        $row = (object) [
            'document_key' => '2666000003786',
            'document_number' => '26-6600-003786',
            'document_type' => '6600',
            'linked_work_order_key' => '2660000003619',
            'work_order_number' => '26-6000-003619',
            'material_code' => 'OP30',
            'material_name' => 'Mašinska obrada - Glodanje',
            'quantity' => 30,
            'unit' => 'RDS',
            'document_price' => 1.51,
            'stored_rn_price' => 1.51,
            'expected_rn_price' => 1.51,
            'is_enalog_created' => 0,
            'raw_note' => '',
        ];

        $mapped = $this->mapRow(new ReleasedOperationDocumentController(), $row);

        $this->assertSame(45.3, $mapped['cijena_rn']);
        $this->assertSame('45.30 KM', $mapped['cijena_rn_display']);
    }

    public function test_material_rn_price_remains_a_unit_price(): void
    {
        $row = (object) [
            'document_key' => '2664000000001',
            'document_type' => '6400',
            'quantity' => 30,
            'document_price' => 1.51,
            'stored_rn_price' => 1.51,
            'expected_rn_price' => 1.51,
            'is_enalog_created' => 0,
            'raw_note' => '',
        ];

        $mapped = $this->mapRow(new ReleasedMaterialDocumentController(), $row);

        $this->assertSame(1.51, $mapped['cijena_rn']);
    }

    public function test_missing_menu_translation_falls_back_to_original_label(): void
    {
        $menuData = (object) ['menu' => [
            (object) ['name' => 'Razdužene operacije', 'slug' => 'operations'],
            (object) ['name' => 'Prijem VP skladište', 'slug' => 'receipt'],
        ]];
        $provider = new MenuServiceProvider($this->app);
        $method = (new ReflectionClass($provider))->getMethod('translateMenuItems');
        $method->setAccessible(true);

        $translated = $method->invoke($provider, $menuData);

        $this->assertSame('Razdužene operacije', $translated->menu[0]->name);
        $this->assertSame('Prijem VP skladište', $translated->menu[1]->name);
    }

    private function mapRow(object $controller, object $row): array
    {
        $method = (new ReflectionClass(ReleasedMaterialDocumentController::class))->getMethod('mapRow');
        $method->setAccessible(true);

        return $method->invoke($controller, $row);
    }
}
