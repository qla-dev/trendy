<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\AiInboxImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class AiInboxController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $pageConfigs = ['pageHeader' => false];
        $rows = OrderAiScan::query()
            ->where('source_origin', 'imap')
            ->orderByRaw('COALESCE(source_email_received_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(function (OrderAiScan $scan) {
                return $this->mapRow($scan);
            })
        );

        return view('content.apps.ai.app-ai-inbox', [
            'pageConfigs' => $pageConfigs,
            'aiInboxRows' => $rows,
        ]);
    }

    public function refresh(Request $request, AiInboxImportService $importService): RedirectResponse
    {
        $this->authorizeAdmin($request);

        try {
            $summary = $importService->importNewMail();

            return redirect()
                ->route('app-order-ai-inbox')
                ->with('aiInboxRefreshSummary', $summary)
                ->with('success', sprintf(
                    'AI Inbox osvjezen: %d mailova, %d PDF-ova, %d duplikata, %d gresaka.',
                    (int) ($summary['imported_messages'] ?? 0),
                    (int) ($summary['imported_pdfs'] ?? 0),
                    (int) ($summary['duplicates_skipped'] ?? 0),
                    (int) ($summary['failed_items'] ?? 0)
                ));
        } catch (Throwable $exception) {
            return redirect()
                ->route('app-order-ai-inbox')
                ->with('error', 'AI Inbox osvjezavanje nije uspjelo: ' . $exception->getMessage());
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $isAdmin = method_exists($user, 'isAdmin')
            ? (bool) $user->isAdmin()
            : (string) ($user->role ?? '') === 'admin';

        if (!$isAdmin) {
            abort(403);
        }
    }

    private function mapRow(OrderAiScan $scan): array
    {
        $aiStatus = $this->resolveAiStatus($scan);
        $transferStatus = $this->resolveTransferStatus($scan);
        $receivedAt = $scan->source_email_received_at instanceof Carbon
            ? $scan->source_email_received_at
            : ($scan->created_at instanceof Carbon ? $scan->created_at : null);

        return [
            'id' => (int) $scan->id,
            'received_at_display' => $receivedAt ? $receivedAt->format('d.m.Y H:i') : '-',
            'from' => trim((string) ($scan->source_email_from ?? '')) ?: '-',
            'subject' => trim((string) ($scan->source_email_subject ?? '')) ?: '-',
            'file_name' => trim((string) ($scan->source_file_name ?? '')) ?: '-',
            'ai_status_label' => $aiStatus['label'],
            'ai_status_tone' => $aiStatus['tone'],
            'transfer_status_label' => $transferStatus['label'],
            'transfer_status_tone' => $transferStatus['tone'],
            'edit_url' => route('app-order-ai-scan', ['scan' => $scan->id]),
        ];
    }

    private function resolveAiStatus(OrderAiScan $scan): array
    {
        return match ((string) ($scan->status ?? '')) {
            'uploaded' => ['label' => 'Ucitan', 'tone' => 'secondary'],
            'extracting' => ['label' => 'AI obrada', 'tone' => 'info'],
            'completed' => ['label' => 'Spremno za pregled', 'tone' => 'success'],
            'ready_for_transfer' => ['label' => 'Spremno za transfer', 'tone' => 'success'],
            'transferring' => ['label' => 'Transfer u toku', 'tone' => 'warning'],
            'transferred' => ['label' => 'Zavrseno', 'tone' => 'success'],
            'failed' => ['label' => 'Neuspjesno', 'tone' => 'danger'],
            default => ['label' => 'Nepoznato', 'tone' => 'secondary'],
        };
    }

    private function resolveTransferStatus(OrderAiScan $scan): array
    {
        if ($scan->transferred_at !== null || trim((string) ($scan->pantheon_order_key ?? '')) !== '') {
            return ['label' => 'Prebaceno u bazu', 'tone' => 'success'];
        }

        if ((string) ($scan->status ?? '') === 'failed' && $this->isTransferFailure($scan)) {
            return ['label' => 'Transfer neuspjesan', 'tone' => 'danger'];
        }

        if (in_array((string) ($scan->status ?? ''), ['completed', 'ready_for_transfer'], true)) {
            return ['label' => 'Rucni pregled', 'tone' => 'primary'];
        }

        if ((string) ($scan->status ?? '') === 'transferring') {
            return ['label' => 'Transfer u toku', 'tone' => 'warning'];
        }

        return ['label' => 'Nije prebaceno', 'tone' => 'secondary'];
    }

    private function isTransferFailure(OrderAiScan $scan): bool
    {
        $processingStep = (string) ($scan->processing_step ?? '');
        $errorMessage = (string) ($scan->error_message ?? '');

        return preg_match('/transfer|baza|pantheon/i', $processingStep . ' ' . $errorMessage) === 1;
    }
}
