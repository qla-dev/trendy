<?php

namespace App\Services\Procurement;

interface ProcurementGateway
{
    /** Must resolve an existing remote document by $idempotencyKey before creating one. */
    public function transfer(array $purchase, string $idempotencyKey): array;
}
