<?php

namespace App\Services\OrderAi\Contracts;

use App\Models\OrderAiScan;

interface OrderAiScanProvider
{
    public function supportsLiveTransfer(): bool;

    public function scan(OrderAiScan $scan): array;
}
