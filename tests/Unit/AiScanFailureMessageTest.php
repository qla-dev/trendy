<?php

namespace Tests\Unit;

use App\Support\AiScanFailureMessage;
use App\Http\Controllers\OrderController;
use App\Services\OrderAi\OrderAiScanService;
use ReflectionClass;
use Tests\TestCase;

class AiScanFailureMessageTest extends TestCase
{
    public function test_foreign_key_failure_has_a_safe_classification_message(): void
    {
        $technical = 'The INSERT statement conflicted with the FOREIGN KEY constraint "rthE_SetItem_thE_SetItemCateg_1". The conflict occurred in database "BA_TRENDY", table "dbo.tHE_SetItemCateg", column "acClassif".';

        $message = AiScanFailureMessage::transfer($technical);

        $this->assertSame('Pantheon nije prihvatio klasifikaciju novog artikla. Pokušajte ponovo ili kontaktirajte administratora.', $message);
        $this->assertStringNotContainsString('INSERT', $message);
        $this->assertStringNotContainsString('dbo.', $message);
    }

    public function test_other_database_details_are_not_shown_to_users(): void
    {
        $this->assertSame(
            'Transfer nije uspio zbog interne greške pri upisu u bazu. Pokušajte ponovo ili kontaktirajte administratora.',
            AiScanFailureMessage::transfer('Pantheon error: The INSERT statement conflicted with the FOREIGN KEY constraint')
        );
        $this->assertNull(AiScanFailureMessage::transfer('Artikal nije pronađen u katalogu.'));
    }

    public function test_manual_and_automatic_transfer_hide_wrapped_sql_errors(): void
    {
        $technical = 'Pantheon nije mogao kreirati katalog artikal 2941558: The INSERT statement conflicted with the FOREIGN KEY constraint, table "dbo.tHE_SetItemCateg", column "acClassif".';
        $controller = new OrderController();
        $manual = (new ReflectionClass($controller))->getMethod('humanizeTransferFailureReason');
        $manual->setAccessible(true);
        $service = app(OrderAiScanService::class);
        $automatic = (new ReflectionClass($service))->getMethod('humanizeTransferFailureMessage');
        $automatic->setAccessible(true);

        $this->assertSame(AiScanFailureMessage::transfer($technical), $manual->invoke($controller, $technical));
        $this->assertSame(AiScanFailureMessage::transfer($technical), $automatic->invoke($service, $technical));
    }
}
