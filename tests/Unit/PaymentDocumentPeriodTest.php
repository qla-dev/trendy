<?php

namespace Tests\Unit;

use App\Http\Controllers\AiTokenHistoryController;
use App\Http\Controllers\ExportPaymentDocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class PaymentDocumentPeriodTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_export_payment_document_uses_requested_month_even_when_unfinished(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07'));

        $controller = app(ExportPaymentDocumentController::class);
        $resolvePeriod = $this->privateMethod($controller, 'resolveBillingPeriod');

        $june = $resolvePeriod->invoke(
            $controller,
            Request::create('/payment', 'GET', ['month' => 6, 'year' => 2026])
        );
        $july = $resolvePeriod->invoke(
            $controller,
            Request::create('/payment', 'GET', ['month' => 7, 'year' => 2026])
        );

        $this->assertSame('2026-06-01', $june['start']->toDateString());
        $this->assertSame('2026-06-30', $june['end']->toDateString());
        $this->assertSame('2026-07-01', $july['start']->toDateString());
        $this->assertSame('2026-07-31', $july['end']->toDateString());
    }

    public function test_payment_document_button_requires_completed_selected_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07'));

        $controller = app(AiTokenHistoryController::class);
        $canShowPaymentDocument = $this->privateMethod($controller, 'canShowPaymentDocument');

        $this->assertTrue($canShowPaymentDocument->invoke($controller, ['month' => 6, 'year' => 2026]));
        $this->assertFalse($canShowPaymentDocument->invoke($controller, ['month' => 7, 'year' => 2026]));
    }

    private function privateMethod(object $object, string $method): ReflectionMethod
    {
        $reflectionMethod = new ReflectionMethod($object, $method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod;
    }
}
