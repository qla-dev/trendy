<?php

namespace Tests\Unit;

use App\Services\OrderAi\PantheonOrderTransferService;
use Carbon\Carbon;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class PantheonOrderTransferServiceProfileTest extends TestCase
{
    public function test_stu_unit_alias_is_normalized_to_default_unit(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeUnitCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'STU', ['acUM' => 3]);

        $this->assertSame('KO', $result);
    }

    public function test_st_unit_alias_is_normalized_to_default_unit(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeUnitCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ST', ['acUM' => 3]);

        $this->assertSame('KO', $result);
    }

    public function test_trendy_germany_does_not_receive_primary_classification(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrimaryClassification');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ALMG3', [
            'supplier_name' => 'Trendy Germany GmbH',
        ]);

        $this->assertSame('', $result);
    }

    public function test_grob_keeps_primary_classification_detection(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrimaryClassification');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ALMG3', [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('ALUMINIJUM', $result);
    }

    public function test_subject_lookup_candidates_include_trendy_de_aliases(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('subjectLookupCandidates');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'Trendy Germany');

        $this->assertContains('Trendy Germany', $result);
        $this->assertContains('Trendy Germany GmbH', $result);

        $numberedResult = $method->invoke($service, 'Trendy Germany GmbH-45');

        $this->assertContains('Trendy Germany GmbH-45', $numberedResult);
        $this->assertContains('Trendy Germany GmbH', $numberedResult);
    }

    public function test_subject_lookup_candidates_preserve_grob_aliases(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('subjectLookupCandidates');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'GROB-WERKE');

        $this->assertContains('GROB-WERKE', $result);
        $this->assertContains('GROB-WERKE GmbH & Co. KG', $result);
    }

    public function test_extract_transfer_item_metadata_ignores_zeichnung_lines_for_grob_orders(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);
        $traeger = hex2bin('5472c3a4676572');

        $result = $method->invoke($service, [
            'product_name' => $traeger . "\nGCU-040-210-01-GM5511/1-1\nZeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00",
            'drawing_reference' => 'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00',
            'note' => "Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00\nPrimarna klasifikacija: CELIK",
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame($traeger . ' GCU-040-210-01-GM5511/1-1', $result['product_name']);
        $this->assertSame('', $result['drawing_reference']);
        $this->assertSame('Primarna klasifikacija: CELIK', $result['note']);
    }

    public function test_extract_transfer_item_metadata_compacts_hyphen_spacing_in_grob_product_name(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'product_name' => "Platte\nGM7258/06 - 1350 - 75/1 - 2",
            'drawing_reference' => '',
            'note' => '',
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('Platte GM7258/06-1350-75/1-2', $result['product_name']);
    }

    public function test_extract_transfer_item_metadata_repairs_spaces_around_german_umlauts(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);
        $u = (string) hex2bin('c3bc');
        $o = (string) hex2bin('c3b6');
        $eszett = (string) hex2bin('c39f');

        $result = $method->invoke($service, [
            'product_name' => 'H ' . $u . " lse\nSt " . $o . ' ' . $eszett . ' el',
            'drawing_reference' => '',
            'note' => 'f ' . $u . ' r Montage',
            'material_hint' => 'br ' . $u . ' niert',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('H' . $u . 'lse St' . $o . $eszett . 'el', $result['product_name']);
        $this->assertSame('f' . $u . 'r Montage', $result['note']);
        $this->assertSame('br' . $u . 'niert', $result['material_hint']);
    }

    public function test_extract_transfer_item_metadata_removes_grob_leading_unit_token_from_product_name(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);
        $durchfuehrung = (string) hex2bin('447572636866c3bc6872756e67');

        $result = $method->invoke($service, [
            'product_name' => 'ST ' . $durchfuehrung . "\nG352-1220-206-0000-06-1",
            'drawing_reference' => '',
            'note' => '',
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame($durchfuehrung . ' G352-1220-206-0000-06-1', $result['product_name']);
        $this->assertStringStartsNotWith('ST ', $result['product_name']);
    }

    public function test_extract_transfer_item_metadata_deduplicates_repeated_grob_product_name_segments(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'product_name' => 'Klotz GM4395/01-70-126/1-2-18 Klotz GM4395/01-70-126/1-2-18',
            'drawing_reference' => '',
            'note' => '',
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('Klotz GM4395/01-70-126/1-2-18', $result['product_name']);
    }

    public function test_normalize_transfer_product_code_strips_decimal_suffix_from_numeric_code(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeTransferProductCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, '64820441.00');

        $this->assertSame('64820441', $result);
    }

    public function test_new_order_header_note_is_always_empty(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildHeaderNote');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($service));
    }

    public function test_grob_requester_code_is_used_as_header_consignee_name(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveHeaderConsigneeName');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'customer_name' => 'Trendy d.o.o.',
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
            'requester_code' => '040',
        ], 'GROB-WERKE GmbH & Co. KG');

        $this->assertSame('040', $result);
    }

    public function test_non_grob_requester_code_does_not_replace_header_consignee_name(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveHeaderConsigneeName');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'customer_name' => 'Trendy Germany GmbH',
            'supplier_name' => 'Trendy Germany GmbH',
            'requester_code' => '040',
        ], 'Trendy Germany GmbH');

        $this->assertSame('Trendy Germany GmbH', $result);
    }

    public function test_grob_item_note_is_cleared_before_pantheon_transfer(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolvePreparedItemNote');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'note' => '02 | Kontierung: U38871-GM7260 | 502,00 Ruesten/Termin abs. 136,00 EUR | Lackierung: RAL 7035 Lichtgrau Glatt.',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('', $result);
    }

    public function test_foreign_0110_order_items_use_export_vat_profile(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveOrderItemVatProfile');
        $method->setAccessible(true);

        $result = $method->invoke($service, '0110', 'P1', 17);

        $this->assertSame('I0', $result['code']);
        $this->assertSame(0.0, $result['rate']);
    }

    public function test_domestic_0200_order_items_use_p1_and_keep_their_rate(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveOrderItemVatProfile');
        $method->setAccessible(true);

        $result = $method->invoke($service, '0200', 'I0', 17);

        $this->assertSame('P1', $result['code']);
        $this->assertSame(17.0, $result['rate']);
    }

    public function test_other_document_types_keep_their_existing_vat_profile(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveOrderItemVatProfile');
        $method->setAccessible(true);

        $result = $method->invoke($service, '0300', 'NN', 5);

        $this->assertSame('NN', $result['code']);
        $this->assertSame(5.0, $result['rate']);
    }

    public function test_dotted_german_delivery_date_is_parsed_for_pantheon(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseDateOrFallback');
        $method->setAccessible(true);

        $result = $method->invoke($service, '1. 6. 2026.', Carbon::parse('2026-01-01'));

        $this->assertSame('2026-06-01', $result->format('Y-m-d'));
    }

    public function test_external_document_date_parser_handles_grob_bestell_date(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseDateOrNull');
        $method->setAccessible(true);

        $result = $method->invoke($service, '09.07.2026');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame('2026-07-09', $result->format('Y-m-d'));
        $this->assertNull($method->invoke($service, ''));
        $this->assertNull($method->invoke($service, 'not a date'));
    }

    public function test_prepare_transfer_data_keeps_external_document_date(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('prepareTransferData');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'order' => [
                'customer_name' => 'Trendy d.o.o.',
                'supplier_name' => 'GROB-WERKE',
                'external_document_date' => '09.07.2026',
            ],
            'items' => [],
            'summary' => [],
        ], false, false, null);

        $this->assertSame('09.07.2026', $result['external_document_date']);
        $this->assertSame('works', $result['server']);
    }

    public function test_trendy_germany_header_blanks_contact_and_pay_method_and_uses_referent_as_odgovorni(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $keyMethod = $reflection->getMethod('tableCacheKey');
        $keyMethod->setAccessible(true);
        $cacheKey = $keyMethod->invoke($service, \App\Models\Order::sourceTableName());
        $columns = [
            'acKey',
            'acKeyView',
            'acDocType',
            'acRefNo1',
            'adDate',
            'adDateDoc1',
            'adDeliveryDate',
            'adDeliveryDeadline',
            'adDateValid',
            'anDaysForValid',
            'acStatus',
            'acConsignee',
            'acReceiver',
            'acContactPrsn',
            'acContactPrsn3',
            'acPayMethod',
            'acCurrency',
            'acWayOfSale',
            'acWarehouse',
            'acDoc1',
            'acDoc2',
            'anValue',
            'anDiscount',
            'anVAT',
            'anForPay',
            'anCurrValue',
            'acNote',
            'acInternalNote',
            'adTimeIns',
            'adTimeChg',
            'anUserIns',
            'anUserChg',
            'anClerk',
            'anNoteClerk',
            'anConsigneeQId',
            'anReceiverQId',
        ];

        $columnsProperty = $reflection->getProperty('orderColumnsCache');
        $columnsProperty->setAccessible(true);
        $columnsProperty->setValue($service, [$cacheKey => $columns]);

        $metadataProperty = $reflection->getProperty('orderColumnMetadataCache');
        $metadataProperty->setAccessible(true);
        $metadataProperty->setValue($service, [$cacheKey => array_fill_keys($columns, ['length' => null])]);

        $nonInsertableProperty = $reflection->getProperty('orderNonInsertableColumnsCache');
        $nonInsertableProperty->setAccessible(true);
        $nonInsertableProperty->setValue($service, [$cacheKey => []]);

        $method = $reflection->getMethod('buildHeaderPayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'acContactPrsn' => 'Edina Duzan',
            'acContactPrsn3' => 'Edina Duzan',
            'acPayMethod' => '2',
            'anConsigneeQId' => 1776,
            'anReceiverQId' => 255,
        ], [
            'customer_name' => 'Trendy Germany GmbH',
            'supplier_name' => 'Trendy Germany GmbH',
            'receiver_name' => 'Trendy Germany 45',
            'contact_name' => 'Edina Duzan',
            'external_document_number' => '26-020-000738',
            'external_document_date' => '21. 5. 2026.',
            'delivery_deadline' => '20. 7. 2026.',
            'document_type' => '0110',
            'currency' => 'EUR',
            'way_of_sale' => 'D',
            'warnings' => [],
            'subtotal' => 2579.9,
            'vat_total' => 0.0,
            'grand_total' => 2579.9,
            'referent_id' => 46,
        ], [
            'raw_key' => '2601100001713',
            'display_key' => '26-0110-001713',
            'doc_type' => '0110',
        ], null, null);

        $this->assertSame('', $result['acContactPrsn']);
        $this->assertSame('', $result['acContactPrsn3']);
        $this->assertSame('', $result['acPayMethod']);
        $this->assertSame('Trendy Germany GmbH-45', $result['acConsignee']);
        $this->assertSame('Trendy Germany GmbH-45', $result['acReceiver']);
        $this->assertSame(1776, $result['anConsigneeQId']);
        $this->assertSame(1776, $result['anReceiverQId']);
        $this->assertSame(46, $result['anClerk']);
        $this->assertSame(46, $result['anNoteClerk']);
        $this->assertSame(46, $result['anUserIns']);
        $this->assertSame(46, $result['anUserChg']);
        $this->assertInstanceOf(Carbon::class, $result['adDateDoc1']);
        $this->assertSame('2026-05-21', $result['adDateDoc1']->format('Y-m-d'));
        $this->assertInstanceOf(Carbon::class, $result['adDeliveryDate']);
        $this->assertInstanceOf(Carbon::class, $result['adDeliveryDeadline']);
        $this->assertSame('2026-07-20', $result['adDeliveryDate']->format('Y-m-d'));
        $this->assertSame('2026-07-20', $result['adDeliveryDeadline']->format('Y-m-d'));
    }

    public function test_header_delivery_dates_are_not_written_when_order_delivery_deadline_is_blank(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $keyMethod = $reflection->getMethod('tableCacheKey');
        $keyMethod->setAccessible(true);
        $cacheKey = $keyMethod->invoke($service, \App\Models\Order::sourceTableName());
        $columns = [
            'acKey',
            'acKeyView',
            'acDocType',
            'acRefNo1',
            'adDate',
            'adDateDoc1',
            'adDeliveryDate',
            'adDeliveryDeadline',
            'adDateValid',
            'anDaysForValid',
            'acStatus',
            'acConsignee',
            'acReceiver',
            'acCurrency',
            'acWayOfSale',
            'acWarehouse',
            'acDoc1',
            'anValue',
            'anDiscount',
            'anVAT',
            'anForPay',
            'anCurrValue',
            'acNote',
            'acInternalNote',
            'adTimeIns',
            'adTimeChg',
        ];

        $columnsProperty = $reflection->getProperty('orderColumnsCache');
        $columnsProperty->setAccessible(true);
        $columnsProperty->setValue($service, [$cacheKey => $columns]);

        $metadataProperty = $reflection->getProperty('orderColumnMetadataCache');
        $metadataProperty->setAccessible(true);
        $metadataProperty->setValue($service, [$cacheKey => array_fill_keys($columns, ['length' => null])]);

        $nonInsertableProperty = $reflection->getProperty('orderNonInsertableColumnsCache');
        $nonInsertableProperty->setAccessible(true);
        $nonInsertableProperty->setValue($service, [$cacheKey => []]);

        $method = $reflection->getMethod('buildHeaderPayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, [], [
            'customer_name' => 'Trendy Germany GmbH',
            'supplier_name' => 'Trendy Germany GmbH',
            'receiver_name' => 'Trendy Germany 45',
            'contact_name' => '',
            'external_document_number' => '26-020-000738',
            'external_document_date' => '21. 5. 2026.',
            'delivery_deadline' => '',
            'document_type' => '0110',
            'currency' => 'EUR',
            'way_of_sale' => 'D',
            'warnings' => [],
            'subtotal' => 2579.9,
            'vat_total' => 0.0,
            'grand_total' => 2579.9,
            'referent_id' => 46,
        ], [
            'raw_key' => '2601100001713',
            'display_key' => '26-0110-001713',
            'doc_type' => '0110',
        ], null, null);

        $this->assertArrayNotHasKey('adDeliveryDate', $result);
        $this->assertArrayNotHasKey('adDeliveryDeadline', $result);
    }

    public function test_order_item_delivery_date_populates_delivery_and_dispatch_dates(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildOrderItemDeliveryDatePayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, Carbon::parse('2026-06-11 14:30:00'));

        $this->assertSame('2026-06-11 00:00:00', $result['adDeliveryDeadline']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-11 00:00:00', $result['adDeliveryDate']->format('Y-m-d H:i:s'));

        $trimMethod = $reflection->getMethod('trimPayloadToInsertableColumns');
        $trimMethod->setAccessible(true);
        $insertPayload = $trimMethod->invoke(
            $service,
            $result,
            ['adDeliveryDeadline', 'adDeliveryDate']
        );

        $this->assertArrayHasKey('adDeliveryDeadline', $insertPayload);
        $this->assertArrayHasKey('adDeliveryDate', $insertPayload);
    }

    public function test_order_item_insert_batches_do_not_mix_optional_note_columns(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildOrderItemInsertBatches');
        $method->setAccessible(true);

        $batches = $method->invoke($service, [
            [
                'acKey' => '2601100001681',
                'acIdent' => '2158279700',
                'anNo' => 1,
            ],
            [
                'acKey' => '2601100001681',
                'acIdent' => 'EXCI40A092020',
                'acNote' => 'NICKEL PLATED',
                'anNo' => 7,
            ],
            [
                'anNo' => 2,
                'acIdent' => 'EVRB10A461050',
                'acKey' => '2601100001681',
            ],
        ]);

        $this->assertCount(2, $batches);
        $this->assertCount(2, $batches[0]);
        $this->assertCount(1, $batches[1]);
        $this->assertSame(array_keys($batches[0][0]), array_keys($batches[0][1]));
        $this->assertArrayNotHasKey('acNote', $batches[0][0]);
        $this->assertArrayHasKey('acNote', $batches[1][0]);
    }

    public function test_pantheon_clerk_resolver_does_not_apply_admin_fallback(): void
    {
        config(['workorders.pantheon_user_map' => []]);

        $service = new class extends PantheonOrderTransferService {
            protected function pantheonClerkContacts(): array
            {
                return [
                    [
                        'id' => 2,
                        'normalized_user_code' => 'ad',
                        'normalized_contact' => 'administrator',
                        'normalized_full_name' => 'administrator',
                        'normalized_name' => 'administrator',
                        'normalized_web_user' => '',
                        'normalized_code' => '',
                        'normalized_worker_contact' => '',
                    ],
                    [
                        'id' => 39,
                        'normalized_user_code' => 'trenkra',
                        'normalized_contact' => 'almakrnjic',
                        'normalized_full_name' => 'almakrnjic',
                        'normalized_name' => 'alma',
                        'normalized_web_user' => '',
                        'normalized_code' => '',
                        'normalized_worker_contact' => '',
                    ],
                ];
            }
        };

        $reflection = new ReflectionClass(PantheonOrderTransferService::class);
        $method = $reflection->getMethod('resolvePantheonClerkUserId');
        $method->setAccessible(true);

        $result = $method->invoke($service, (object) [
            'id' => 1,
            'name' => 'Demo Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->assertSame(0, $result);
    }

    public function test_pantheon_clerk_resolver_matches_any_contact_full_name(): void
    {
        config(['workorders.pantheon_user_map' => []]);

        $service = new class extends PantheonOrderTransferService {
            protected function pantheonClerkContacts(): array
            {
                return [
                    [
                        'id' => 39,
                        'normalized_user_code' => 'trenkra',
                        'normalized_contact' => 'almakrnjic',
                        'normalized_full_name' => 'almakrnjic',
                        'normalized_name' => 'alma',
                        'normalized_web_user' => '',
                        'normalized_code' => '',
                        'normalized_worker_contact' => '',
                    ],
                    [
                        'id' => 58,
                        'normalized_user_code' => 'trenleh',
                        'normalized_contact' => 'elvirperva',
                        'normalized_full_name' => 'elvirperva',
                        'normalized_name' => 'elvir',
                        'normalized_web_user' => '',
                        'normalized_code' => '',
                        'normalized_worker_contact' => '',
                    ],
                ];
            }
        };

        $reflection = new ReflectionClass(PantheonOrderTransferService::class);
        $method = $reflection->getMethod('resolvePantheonClerkUserId');
        $method->setAccessible(true);

        $result = $method->invoke($service, (object) [
            'name' => 'Elvir Perva',
            'username' => 'elvir.perva',
        ]);

        $this->assertSame(58, $result);
    }

    public function test_pantheon_referent_payload_contains_display_name_and_user_code(): void
    {
        $service = new class extends PantheonOrderTransferService {
            protected function pantheonClerkContacts(): array
            {
                return [
                    [
                        'id' => 34,
                        'display_name' => 'Selvina Silajdžija',
                        'user_code' => 'TREN_SIS',
                        'normalized_user_code' => 'trensis',
                        'normalized_contact' => 'selvinasilajdzija',
                        'normalized_full_name' => 'selvinasilajdzija',
                        'normalized_web_user' => '',
                        'normalized_code' => '',
                        'normalized_worker_contact' => '',
                    ],
                ];
            }
        };

        $reflection = new ReflectionClass(PantheonOrderTransferService::class);
        $method = $reflection->getMethod('resolvePantheonReferentPayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, 34);

        $this->assertSame([
            'id' => 34,
            'name' => 'Selvina Silajdžija',
            'user_code' => 'TREN_SIS',
        ], $result);
    }

    public function test_duplicate_external_document_reference_is_blocked_before_transfer(): void
    {
        $service = new class extends PantheonOrderTransferService {
            public function assertUniqueReferenceForTest(array $prepared): void
            {
                $this->assertUniqueExternalDocumentReference($prepared);
            }

            protected function findExistingOrderByExternalDocumentReference(string $reference): ?array
            {
                return [
                    'key' => '260110001161',
                    'view' => '26-0110-001161',
                    'reference' => $reference,
                ];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Narudžba sa referencom "4512109382" već postoji u bazi kao 26-0110-001161.');

        $service->assertUniqueReferenceForTest([
            'external_document_number' => '4512109382',
        ]);
    }
}
