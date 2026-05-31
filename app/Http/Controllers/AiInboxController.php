<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\AiInboxImportService;
use App\Services\OrderAi\AiInboxSenderDirectory;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class AiInboxController extends Controller
{
    public function __construct(private readonly AiInboxSenderDirectory $senderDirectory)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeModuleAccess($request);

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
            'aiInboxLastLoadedAtDisplay' => now()->format('d.m.Y H:i:s'),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $this->authorizeModuleAccess($request);

        $ids = $this->resolveRequestedIds($request);

        if ($ids === []) {
            return response()->json([
                'rows' => [],
                'last_loaded_at' => now()->toIso8601String(),
                'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
            ]);
        }

        $rows = OrderAiScan::query()
            ->where('source_origin', 'imap')
            ->whereIn('id', $ids)
            ->get([
                'id',
                'status',
                'processing_step',
                'error_message',
                'transferred_at',
                'pantheon_order_key',
            ]);

        return response()->json([
            'rows' => $rows->mapWithKeys(function (OrderAiScan $scan) {
                return [(string) $scan->id => $this->mapStatusRow($scan)];
            })->all(),
            'last_loaded_at' => now()->toIso8601String(),
            'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
        ]);
    }

    public function refresh(Request $request, AiInboxImportService $importService): RedirectResponse
    {
        $this->authorizeModuleAccess($request);

        try {
            $summary = $importService->importNewMail();

            return redirect()
                ->route('app-order-ai-inbox')
                ->with('aiInboxRefreshSummary', $summary)
                ->with('success', sprintf(
                    'AI Inbox osvježen: %d mailova, %d PDF-ova, %d duplikata, %d blokiranih, %d grešaka.',
                    (int) ($summary['imported_messages'] ?? 0),
                    (int) ($summary['imported_pdfs'] ?? 0),
                    (int) ($summary['duplicates_skipped'] ?? 0),
                    (int) ($summary['blocked_messages'] ?? 0),
                    (int) ($summary['failed_items'] ?? 0)
                ));
        } catch (Throwable $exception) {
            return redirect()
                ->route('app-order-ai-inbox')
                ->with('error', 'AI Inbox osvježavanje nije uspjelo: ' . Utf8Sanitizer::cleanExceptionMessage($exception));
        }
    }

    private function authorizeModuleAccess(Request $request): void
    {
        $user = $request->user();

        if (!$this->userCanAccessAiOrderModule($user)) {
            abort(403);
        }
    }

    private function userCanAccessAiOrderModule($user): bool
    {
        if ($user === null) {
            return false;
        }

        return method_exists($user, 'canAccessAiOrderModule')
            ? (bool) $user->canAccessAiOrderModule()
            : false;
    }

    private function mapRow(OrderAiScan $scan): array
    {
        $receivedAt = $scan->source_email_received_at instanceof Carbon
            ? $scan->source_email_received_at
            : ($scan->created_at instanceof Carbon ? $scan->created_at : null);
        $senderMeta = $this->senderDirectory->parseAddress((string) ($scan->source_email_from ?? ''));

        return array_merge([
            'id' => (int) $scan->id,
            'received_at_display' => $receivedAt ? $receivedAt->format('d.m.Y H:i') : '-',
            'from' => $this->senderDirectory->resolveDisplayLabel($senderMeta['email'] ?? '', $senderMeta['name'] ?? ''),
            'subject' => trim((string) ($scan->source_email_subject ?? '')) ?: '-',
            'file_name' => trim((string) ($scan->source_file_name ?? '')) ?: '-',
            'edit_url' => route('app-order-ai-scan', ['scan' => $scan->id]),
        ], $this->mapStatusRow($scan));
    }

    private function mapStatusRow(OrderAiScan $scan): array
    {
        $aiStatus = $this->resolveAiStatus($scan);
        $transferStatus = $this->resolveTransferStatus($scan);

        return [
            'id' => (int) $scan->id,
            'ai_status_label' => $aiStatus['label'],
            'ai_status_tone' => $aiStatus['tone'],
            'transfer_status_label' => $transferStatus['label'],
            'transfer_status_tone' => $transferStatus['tone'],
        ];
    }

    private function resolveAiStatus(OrderAiScan $scan): array
    {
        return match ((string) ($scan->status ?? '')) {
            'uploaded' => ['label' => 'Učitan', 'tone' => 'secondary'],
            'extracting' => ['label' => 'AI obrada', 'tone' => 'info'],
            'completed' => ['label' => 'Spremno za pregled', 'tone' => 'success'],
            'ready_for_transfer' => ['label' => 'Spremno za transfer', 'tone' => 'success'],
            'transferring' => ['label' => 'Transfer u toku', 'tone' => 'warning'],
            'transferred' => ['label' => 'Završeno', 'tone' => 'success'],
            'failed' => ['label' => 'Neuspješno', 'tone' => 'danger'],
            default => ['label' => 'Nepoznato', 'tone' => 'secondary'],
        };
    }

    private function resolveTransferStatus(OrderAiScan $scan): array
    {
        if ($scan->transferred_at !== null || trim((string) ($scan->pantheon_order_key ?? '')) !== '') {
            return ['label' => 'Prebačeno u bazu', 'tone' => 'success'];
        }

        if ((string) ($scan->status ?? '') === 'failed' && $this->isTransferFailure($scan)) {
            return ['label' => 'Transfer neuspješan', 'tone' => 'danger'];
        }

        if (in_array((string) ($scan->status ?? ''), ['completed', 'ready_for_transfer'], true)) {
            return ['label' => 'Ručni pregled', 'tone' => 'primary'];
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

    private function resolveRequestedIds(Request $request): array
    {
        $values = $request->query('ids', []);

        if (!is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->map(function ($value) {
                return (int) $value;
            })
            ->filter(function (int $value) {
                return $value > 0;
            })
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }
}
