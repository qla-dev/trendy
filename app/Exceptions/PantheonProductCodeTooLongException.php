<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an order item carries an article code that does not fit the
 * Pantheon catalog identifier column, so the article cannot be created.
 * The transfer UI turns this into a prompt for a shortened code.
 */
class PantheonProductCodeTooLongException extends RuntimeException
{
    public function __construct(
        private readonly string $productCode,
        private readonly int $maxLength
    ) {
        parent::__construct(sprintf(
            'Šifra artikla %s ima %d znakova, a Pantheon katalog dozvoljava najviše %d. Skratite šifru pa pokušajte ponovo.',
            $productCode,
            mb_strlen($productCode),
            $maxLength
        ));
    }

    public function productCode(): string
    {
        return $this->productCode;
    }

    public function maxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Longest prefix of the code that Pantheon would accept, offered to the
     * user as the starting point for the shortened code.
     */
    public function suggestedProductCode(): string
    {
        return rtrim(mb_substr($this->productCode, 0, $this->maxLength), " .-_/");
    }
}
