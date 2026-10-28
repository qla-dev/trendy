<?php

namespace App\Services\Procurement;

class UnconfiguredPantheonProcurementGateway implements ProcurementGateway
{
    public function transfer(array $purchase, string $idempotencyKey): array
    {
        throw new \LogicException('Dokument 0205 je poznat, ali nisu potvrđeni smjer subjekata, obavezna polja, veze prijema i ERP idempotentnost.');
    }
}
