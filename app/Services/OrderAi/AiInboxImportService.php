<?php

namespace App\Services\OrderAi;

use App\Models\OrderAiScan;
use App\Support\Utf8Sanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client as ImapClient;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class AiInboxImportService
{
    private ?string $mailboxDelimiter = null;

    public function __construct(private readonly OrderAiScanService $scanService)
    {
    }

    public function importNewMail(): array
    {
        $summary = [
            'matched_messages' => 0,
            'imported_messages' => 0,
            'imported_pdfs' => 0,
            'duplicates_skipped' => 0,
            'failed_items' => 0,
        ];

        $config = $this->inboxConfig();

        if (!$this->isEnabled()) {
            throw new RuntimeException('AI Inbox mailbox nije konfigurisan.');
        }

        /** @var ImapClient $client */
        $client = (new ClientManager($this->buildImapClientConfig($config)))->account('default');
        $client->connect();

        try {
            $sourceFolderPath = $this->folderPath('source', $client);
            $sourceFolder = $client->getFolderByPath($sourceFolderPath, false, true);

            if ($sourceFolder === null) {
                throw new RuntimeException('Source mailbox folder nije pronađen: ' . $sourceFolderPath);
            }

            $messages = $sourceFolder->messages()->all()->get();
            $sortedMessages = collect($messages->all())
                ->sortBy(function (Message $message) {
                    return (int) $message->getUid();
                })
                ->values();

            foreach ($sortedMessages as $message) {
                $subject = trim((string) $message->getSubject());

                if (!$this->subjectMatches($subject, (string) ($config['subject_keyword'] ?? 'Bestellung'))) {
                    continue;
                }

                $summary['matched_messages']++;
                $messageResult = $this->importMessage($message, $subject);

                $summary['imported_pdfs'] += $messageResult['imported_pdfs'];
                $summary['duplicates_skipped'] += $messageResult['duplicates_skipped'];
                $summary['failed_items'] += $messageResult['failed_items'];

                if ($messageResult['imported_pdfs'] > 0) {
                    $summary['imported_messages']++;
                }
            }
        } finally {
            $client->disconnect();
        }

        return $summary;
    }

    public function isEnabled(): bool
    {
        return filter_var(config('ai-order-scan.inbox.enabled', true), FILTER_VALIDATE_BOOL)
            && $this->isConfigured($this->inboxConfig());
    }

    private function importMessage(Message $message, string $subject): array
    {
        $pdfAttachments = collect($message->getAttachments()->all())
            ->filter(function ($attachment) {
                return $attachment instanceof Attachment && $this->isPdfAttachment($attachment);
            })
            ->values();

        $result = [
            'imported_pdfs' => 0,
            'duplicates_skipped' => 0,
            'failed_items' => 0,
        ];

        if ($pdfAttachments->isEmpty()) {
            $this->moveMessage($message, 'review');

            return [
                'imported_pdfs' => 0,
                'duplicates_skipped' => 0,
                'failed_items' => 1,
            ];
        }

        $uid = trim((string) $message->getUid());
        $from = $this->resolveSender($message);
        $messageId = trim((string) $message->getMessageId());
        $receivedAt = $this->resolveMessageDate($message);
        $hasFailures = false;

        foreach ($pdfAttachments as $index => $attachment) {
            $attachmentIndex = $index + 1;
            $scan = null;

            if ($this->scanAlreadyImported($uid, $attachmentIndex)) {
                $result['duplicates_skipped']++;
                continue;
            }

            try {
                $originalName = $this->resolveAttachmentName($attachment, $attachmentIndex);
                $binaryContent = (string) $attachment->getContent();
                $mimeType = (string) ($attachment->getMimeType() ?: 'application/pdf');

                if ($binaryContent === '') {
                    throw new RuntimeException('PDF privitak je prazan.');
                }

                $scan = $this->scanService->createScanFromBinary(
                    originalName: $originalName,
                    binaryContent: $binaryContent,
                    mimeType: $mimeType,
                    user: null,
                    attributes: [
                        'source_origin' => 'imap',
                        'source_email_subject' => $subject,
                        'source_email_from' => $from,
                        'source_email_message_id' => $messageId !== '' ? $messageId : null,
                        'source_email_uid' => $uid !== '' ? $uid : null,
                        'source_email_received_at' => $receivedAt,
                        'source_attachment_index' => $attachmentIndex,
                        'source_attachment_total' => $pdfAttachments->count(),
                    ]
                );

                $this->scanService->dispatchBackgroundProcessing($scan);
                $result['imported_pdfs']++;
            } catch (Throwable $exception) {
                $hasFailures = true;
                $result['failed_items']++;
                $sanitizedMessage = Utf8Sanitizer::cleanExceptionMessage($exception);

                if ($scan instanceof OrderAiScan) {
                    $scan->forceFill([
                        'status' => 'failed',
                        'processing_step' => 'AI inbox import nije uspio.',
                        'error_message' => $sanitizedMessage,
                        'completed_at' => now(),
                    ])->save();
                }

                Log::warning('AI inbox attachment import failed.', [
                    'message_uid' => $uid,
                    'attachment_index' => $attachmentIndex,
                    'subject' => $subject,
                    'message' => $sanitizedMessage,
                ]);
            }
        }

        $this->moveMessage($message, $hasFailures ? 'review' : 'processed');

        return $result;
    }

    private function scanAlreadyImported(string $uid, int $attachmentIndex): bool
    {
        if ($uid === '') {
            return false;
        }

        return OrderAiScan::query()
            ->where('source_origin', 'imap')
            ->where('source_email_uid', $uid)
            ->where('source_attachment_index', $attachmentIndex)
            ->exists();
    }

    private function resolveAttachmentName(Attachment $attachment, int $attachmentIndex): string
    {
        $name = trim((string) ($attachment->getName() ?: $attachment->filename ?: ''));

        if ($name !== '') {
            return $name;
        }

        return 'bestellung-' . $attachmentIndex . '.pdf';
    }

    private function resolveSender(Message $message): string
    {
        $from = $message->getFrom();
        $address = is_object($from) && method_exists($from, 'first')
            ? $from->first()
            : null;

        if (is_object($address)) {
            $full = trim((string) ($address->full ?? ''));

            if ($full !== '') {
                return $full;
            }

            $mail = trim((string) ($address->mail ?? ''));

            if ($mail !== '') {
                return $mail;
            }
        }

        return '';
    }

    private function resolveMessageDate(Message $message): ?Carbon
    {
        $value = $message->getDate();
        $date = is_object($value) && method_exists($value, 'first')
            ? $value->first()
            : $value;

        if ($date instanceof Carbon) {
            return $date;
        }

        if (is_string($date) && trim($date) !== '') {
            try {
                return Carbon::parse($date);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function moveMessage(Message $message, string $targetKey): void
    {
        $client = $message->getClient();

        if (!$client instanceof ImapClient) {
            return;
        }

        $targetPath = $this->folderPath($targetKey, $client);
        $sourcePath = $this->folderPath('source', $client);

        if ($targetPath === '' || $targetPath === $sourcePath) {
            return;
        }

        if ($client->getFolderByPath($targetPath, false, true) === null) {
            $client->createFolder($targetPath);
        }

        $message->move($targetPath);
    }

    private function isPdfAttachment(Attachment $attachment): bool
    {
        $name = strtolower(trim((string) ($attachment->getName() ?: $attachment->filename ?: '')));
        $mimeType = strtolower(trim((string) $attachment->getMimeType()));

        return str_ends_with($name, '.pdf') || str_contains($mimeType, 'pdf');
    }

    private function subjectMatches(string $subject, string $keyword): bool
    {
        return mb_stripos($subject, $keyword) !== false;
    }

    private function buildImapClientConfig(array $config): array
    {
        $imap = $config['imap'] ?? [];

        return [
            'default' => 'default',
            'accounts' => [
                'default' => [
                    'host' => (string) ($imap['host'] ?? ''),
                    'port' => (int) ($imap['port'] ?? 993),
                    'protocol' => (string) ($imap['protocol'] ?? 'imap'),
                    'encryption' => $imap['encryption'] ?? 'ssl',
                    'validate_cert' => (bool) ($imap['validate_cert'] ?? true),
                    'username' => (string) ($imap['username'] ?? ''),
                    'password' => (string) ($imap['password'] ?? ''),
                    'authentication' => $imap['authentication'] ?? null,
                    'timeout' => (int) ($imap['timeout'] ?? 30),
                ],
            ],
            'options' => [
                'fetch' => \Webklex\PHPIMAP\IMAP::FT_PEEK,
                'fetch_order' => 'asc',
                'message_key' => 'uid',
                'dispositions' => ['attachment', 'inline'],
                'open' => is_array($imap['open'] ?? null) ? $imap['open'] : [],
            ],
        ];
    }

    private function folderPath(string $key, ?ImapClient $client = null): string
    {
        $path = trim((string) config('ai-order-scan.inbox.folders.' . $key, ''));

        if ($path === '' || !$client instanceof ImapClient) {
            return $path;
        }

        return $this->normalizeFolderPath($path, $client);
    }

    private function inboxConfig(): array
    {
        return (array) config('ai-order-scan.inbox', []);
    }

    private function isConfigured(array $config): bool
    {
        $imap = (array) ($config['imap'] ?? []);

        return trim((string) ($imap['host'] ?? '')) !== ''
            && trim((string) ($imap['username'] ?? '')) !== ''
            && trim((string) ($imap['password'] ?? '')) !== '';
    }

    private function normalizeFolderPath(string $path, ImapClient $client): string
    {
        $delimiter = $this->resolveMailboxDelimiter($client);

        if ($delimiter === '') {
            return trim($path);
        }

        return preg_replace('#[\\\\/]#', preg_quote($delimiter, '#') === '\.' ? '.' : $delimiter, trim($path)) ?? trim($path);
    }

    private function resolveMailboxDelimiter(ImapClient $client): string
    {
        if ($this->mailboxDelimiter !== null) {
            return $this->mailboxDelimiter;
        }

        $folders = $client->getFolders(false, null, true);
        $inboxFolder = $folders->first(function ($folder) {
            return $folder instanceof \Webklex\PHPIMAP\Folder
                && strtoupper((string) $folder->path) === 'INBOX';
        });

        if ($inboxFolder instanceof \Webklex\PHPIMAP\Folder && trim((string) $inboxFolder->delimiter) !== '') {
            $this->mailboxDelimiter = (string) $inboxFolder->delimiter;

            return $this->mailboxDelimiter;
        }

        $firstFolder = $folders->first();

        if ($firstFolder instanceof \Webklex\PHPIMAP\Folder && trim((string) $firstFolder->delimiter) !== '') {
            $this->mailboxDelimiter = (string) $firstFolder->delimiter;

            return $this->mailboxDelimiter;
        }

        $this->mailboxDelimiter = '/';

        return $this->mailboxDelimiter;
    }
}
