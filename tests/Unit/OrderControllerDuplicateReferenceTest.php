<?php

namespace Tests\Unit;

use App\Http\Controllers\OrderController;
use ReflectionMethod;
use Tests\TestCase;

class OrderControllerDuplicateReferenceTest extends TestCase
{
    public function test_duplicate_external_reference_is_classified_separately_from_a_transfer_failure(): void
    {
        $controller = new OrderController();
        $message = 'Narudžba sa referencom "4512133412" već postoji u bazi kao 26-0110-001768.';

        $isDuplicate = new ReflectionMethod($controller, 'isDuplicateExternalDocumentReferenceFailure');
        $isDuplicate->setAccessible(true);
        $existingOrder = new ReflectionMethod($controller, 'extractExistingOrderViewFromDuplicateReferenceMessage');
        $existingOrder->setAccessible(true);

        $this->assertTrue($isDuplicate->invoke($controller, $message));
        $this->assertSame('26-0110-001768', $existingOrder->invoke($controller, $message));
        $this->assertFalse($isDuplicate->invoke($controller, 'Pantheon connection timed out.'));
    }
}
