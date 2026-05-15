<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class InsufficientRawMaterialStockException extends RuntimeException
{
    private string $detail;

    public function __construct(string $detail = '', ?Throwable $previous = null)
    {
        $message = 'Nedostaje zaliha na skladištu sirovina.';

        parent::__construct($message, 0, $previous);

        $this->detail = trim($detail) !== '' ? trim($detail) : $message;
    }

    public function detail(): string
    {
        return $this->detail;
    }
}
