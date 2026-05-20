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
                            'quantity',
                            'unit',
                            'unit_price',
                            'vat_rate',
                            'vat_code',
                            'discount_percent',
                            'priority',
                            'note',
                        ],
                        'properties' => [
                            'line_number' => ['type' => 'integer'],
                            'product_code' => ['type' => 'string'],
                            'product_name' => ['type' => 'string'],
                            'quantity' => ['type' => 'number'],
                            'unit' => ['type' => 'string'],
                            'unit_price' => ['type' => 'number'],
                            'vat_rate' => ['type' => 'number'],
                            'vat_code' => ['type' => 'string'],
                            'discount_percent' => ['type' => 'number'],
                            'priority' => ['type' => 'string'],
                            'note' => ['type' => 'string'],
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
