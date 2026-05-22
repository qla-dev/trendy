<?php

namespace App\Console\Commands;

use App\Services\OrderAi\AiInboxImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollAiInboxCommand extends Command
{
    protected $signature = 'orders:ai-inbox-poll';

    protected $description = 'Poll the configured AI inbox mailbox and import new Bestellung PDF attachments.';

    public function handle(AiInboxImportService $importService): int
    {
        if (!$importService->isEnabled()) {
            $this->line('AI Inbox polling je onemogucen.');

            return self::SUCCESS;
        }

        try {
            $summary = $importService->importNewMail();

            $this->info(sprintf(
                'AI Inbox sync: %d mailova, %d PDF-ova, %d duplikata, %d gresaka.',
                (int) ($summary['imported_messages'] ?? 0),
                (int) ($summary['imported_pdfs'] ?? 0),
                (int) ($summary['duplicates_skipped'] ?? 0),
                (int) ($summary['failed_items'] ?? 0)
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('AI inbox polling failed.', [
                'message' => $exception->getMessage(),
            ]);

            $this->error('AI Inbox polling nije uspio: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
