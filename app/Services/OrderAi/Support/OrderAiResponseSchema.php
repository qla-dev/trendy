<?php

namespace App\Services\OrderAi\Support;

class OrderAiResponseSchema
{
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['order', 'items', 'summary'],
            'properties' => [
                'order' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => [
                        'customer_name',
                        'supplier_name',
                        'page_count',
                        'receiver_name',
                        'contact_name',
                        'external_document_number',
                        'document_type',
                        'currency',
                        'delivery_deadline',
                        'note',
                        'way_of_sale',
                        'confidence',
                        'warnings',
                    ],
                    'properties' => [
                        'customer_name' => ['type' => 'string'],
                        'supplier_name' => ['type' => 'string'],
                        'page_count' => [
                            'type' => 'integer',
                            'description' => 'Total number of pages in the uploaded file.',
                        ],
                        'receiver_name' => ['type' => 'string'],
                        'contact_name' => ['type' => 'string'],
                        'external_document_number' => ['type' => 'string'],
                        'document_type' => ['type' => 'string'],
                        'currency' => ['type' => 'string'],
                        'delivery_deadline' => ['type' => 'string'],
                        'note' => ['type' => 'string'],
                        'way_of_sale' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                        'warnings' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'line_number',
                            'product_code',
                            'product_name',
                            'drawing_reference',
                            'material_hint',
                            'quantity',
                            'unit',
                            'unit_price',
                            'line_total',
                            'vat_rate',
                            'vat_code',
                            'discount_percent',
                            'priority',
                            'note',
                        ],
                        'properties' => [
                            'line_number' => ['type' => 'integer'],
                            'product_code' => [
                                'type' => 'string',
                                'description' => 'Visible item/material code only. Keep it separate from the long item description.',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'Visible article/name block only. Merge stacked article-name lines that belong to the same item, but exclude lines that begin with Zeichnung and exclude Werkstoff lines.',
                            ],
                            'drawing_reference' => [
                                'type' => 'string',
                                'description' => 'Optional drawing/reference text such as a line that starts with Zeichnung. Keep it separate from product_name.',
                            ],
                            'material_hint' => [
                                'type' => 'string',
                                'description' => 'The visible value after Werkstoff:, without the Werkstoff label.',
                            ],
                            'quantity' => ['type' => 'number'],
                            'unit' => ['type' => 'string'],
                            'unit_price' => [
                                'type' => 'number',
                                'description' => 'Final visible Nettopreis for the item. If a page ends with Bruttopreis and the next page continues the same item without a new position/code, use the continued Nettopreis instead.',
                            ],
                            'line_total' => [
                                'type' => 'number',
                                'description' => 'Final visible row total/Wert for the item. Include continuation amounts that belong to the previous item and do not leave them only in the summary.',
                            ],
                            'vat_rate' => ['type' => 'number'],
                            'vat_code' => ['type' => 'string'],
                            'discount_percent' => ['type' => 'number'],
                            'priority' => ['type' => 'string'],
                            'note' => [
                                'type' => 'string',
                                'description' => 'Optional extra note that belongs to the item, such as delivery/date notes that continue the same line.',
                            ],
                        ],
                    ],
                ],
                'summary' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['subtotal', 'vat_total', 'grand_total'],
                    'properties' => [
                        'subtotal' => ['type' => 'number'],
                        'vat_total' => ['type' => 'number'],
                        'grand_total' => ['type' => 'number'],
                    ],
                ],
            ],
        ];
    }
}
