<?php

namespace App\Services\Procurement;

interface ProcurementReceiptGateway
{
    public function receive(array $purchase, array $receipt, string $idempotencyKey): array;
}
