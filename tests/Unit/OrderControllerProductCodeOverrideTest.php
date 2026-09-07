<?php

namespace Tests\Unit;

use App\Exceptions\PantheonProductCodeTooLongException;
use App\Http\Controllers\OrderController;
use ReflectionMethod;
use Tests\TestCase;

class OrderControllerProductCodeOverrideTest extends TestCase
{
    private function applyOverrides(array $payload, array $overrides): array
    {
        $method = new ReflectionMethod(OrderController::class, 'applyProductCodeOverrides');
        $method->setAccessible(true);

        return $method->invoke(new OrderController(), $payload, $overrides);
    }

    public function test_a_shortened_code_replaces_the_scanned_one_on_the_matching_item(): void
    {
        $payload = $this->applyOverrides([
            'items' => [
                ['product_code' => '3.022-3030-015-30', 'product_name' => 'TRAPEZGEWINDESPINDEL'],
                ['product_code' => '7039742', 'product_name' => 'Ostalo'],
            ],
        ], ['3.022-3030-015-30' => '3.022-3030-015']);

        $this->assertSame('3.022-3030-015', $payload['items'][0]['product_code']);
        $this->assertSame('7039742', $payload['items'][1]['product_code']);
    }

    public function test_codes_are_matched_regardless_of_separators_and_case(): void
    {
        $payload = $this->applyOverrides([
            'items' => [['product_code' => '3.022-3030-015-30']],
        ], ['3022 3030 015 30' => 'TR-20X4-LI']);

        $this->assertSame('TR-20X4-LI', $payload['items'][0]['product_code']);
    }

    public function test_blank_and_unmatched_overrides_leave_the_payload_untouched(): void
    {
        $payload = $this->applyOverrides([
            'items' => [['product_code' => '7039742']],
        ], ['3.022-3030-015-30' => '', 'A-1' => 'B-2']);

        $this->assertSame('7039742', $payload['items'][0]['product_code']);
    }

    public function test_too_long_code_exception_offers_a_code_that_fits(): void
    {
        $exception = new PantheonProductCodeTooLongException('3.022-3030-015-30', 16);

        $this->assertSame('3.022-3030-015-30', $exception->productCode());
        $this->assertSame(16, $exception->maxLength());
        $this->assertSame('3.022-3030-015-3', $exception->suggestedProductCode());
        $this->assertLessThanOrEqual(16, mb_strlen($exception->suggestedProductCode()));
    }

    public function test_suggested_code_does_not_end_on_a_dangling_separator(): void
    {
        $exception = new PantheonProductCodeTooLongException('3.022-3030-0155-30', 15);

        $this->assertSame('3.022-3030-0155', $exception->suggestedProductCode());
    }
}
