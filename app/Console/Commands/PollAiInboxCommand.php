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
            Log::warning('AI inbox polling skipped because the inbox is disabled or incomplete.', [
                'enabled' => filter_var(config('ai-order-scan.inbox.enabled', true), FILTER_VALIDATE_BOOL),
                'poll_interval_minutes' => max(1, (int) config('ai-order-scan.inbox.poll_interval_minutes', 1)),
                'subject_keyword' => (string) config('ai-order-scan.inbox.subject_keyword', 'Bestellung'),
                'imap_host_configured' => trim((string) config('ai-order-scan.inbox.imap.host', '')) !== '',
                'imap_username_configured' => trim((string) config('ai-order-scan.inbox.imap.username', '')) !== '',
                'imap_password_configured' => trim((string) config('ai-order-scan.inbox.imap.password', '')) !== '',
                'queue_connection' => (string) config('ai-order-scan.inbox.queue_connection', ''),
                'queue_name' => (string) config('ai-order-scan.inbox.queue_name', ''),
            ]);

            $this->line('AI Inbox polling je onemogucen.');

            return self::SUCCESS;
        }

        try {
            Log::info('AI inbox polling started.', [
                'poll_interval_minutes' => max(1, (int) config('ai-order-scan.inbox.poll_interval_minutes', 1)),
                'subject_keyword' => (string) config('ai-order-scan.inbox.subject_keyword', 'Bestellung'),
                'queue_connection' => (string) config('ai-order-scan.inbox.queue_connection', 'database_ai_inbox'),
                'queue_name' => (string) config('ai-order-scan.inbox.queue_name', 'ai-inbox'),
            ]);

            $summary = $importService->importNewMail();

            Log::info('AI inbox polling finished.', $summary);

            $this->info(sprintf(
                'AI Inbox sync: %d/%d mailova, %d PDF-ova, %d blokiranih, %d preskocenih po naslovu, %d duplikata, %d gresaka.',
                (int) ($summary['imported_messages'] ?? 0),
                (int) ($summary['matched_messages'] ?? 0),
                (int) ($summary['imported_pdfs'] ?? 0),
                (int) ($summary['blocked_messages'] ?? 0),
                (int) ($summary['subject_skipped'] ?? 0),
                (int) ($summary['duplicates_skipped'] ?? 0),
                (int) ($summary['failed_items'] ?? 0)
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('AI inbox polling failed.', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            $this->error('AI Inbox polling nije uspio: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
