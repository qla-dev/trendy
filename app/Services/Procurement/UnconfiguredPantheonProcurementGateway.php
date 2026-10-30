<?php

namespace App\Services\Procurement;

class UnconfiguredPantheonProcurementGateway implements ProcurementGateway
{
    public function transfer(array $purchase, string $idempotencyKey): array
    {
        throw new \LogicException('Pantheon adapter nije konfigurisan.');
    }
}
