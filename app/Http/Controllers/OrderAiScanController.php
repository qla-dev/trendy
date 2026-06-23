<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use App\Support\AiTokenNavbarCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderAiScanController extends Controller
{
    public function index(Request $request, OrderAiScanService $scanService)
    {
        $this->authorizeModuleAccess($request);

        $pageConfigs = ['pageHeader' => false];
        $initialScan = null;
        $initialScanState = null;
        $requestedScanId = (int) $request->query('scan', 0);
        $openedFromHistory = $request->boolean('history');

        if ($requestedScanId > 0) {
            $initialScan = OrderAiScan::query()->findOrFail($requestedScanId);
            $this->authorizeScan($request, $initialScan);
            $initialScanState = $this->buildScanStatusPayload($scanService, $initialScan);
        }

        return view('content.apps.orders.app-order-ai-scan', [
            'pageConfigs' => $pageConfigs,
            'scanProvider' => (string) config('ai-order-scan.provider', 'mock'),
            'scanModel' => (string) config('ai-order-scan.model', 'gpt-5'),
            'autoTransferEnabled' => filter_var(config('ai-order-scan.auto_transfer', false), FILTER_VALIDATE_BOOL),
            'initialScanId' => $initialScan?->id,
            'initialScanState' => $initialScanState,
            'openedFromHistory' => $openedFromHistory && $initialScan !== null,
        ]);
    }

    public function store(Request $request, OrderAiScanService $scanService): JsonResponse
    {
        $this->authorizeModuleAccess($request);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $scan = $scanService->createScan($validated['file'], $request->user());

        return $this->jsonNoStore(
            $this->buildScanResponsePayload($scanService, $scan, 'Dokument je učitan i spreman za AI obradu.'),
            201
        );
    }

    public function status(Request $request, OrderAiScan $scan, OrderAiScanService $scanService): JsonResponse
    {
        $this->authorizeModuleAccess($request);
        $this->authorizeScan($request, $scan);

        if ((string) ($scan->source_origin ?? 'manual') === 'imap') {
            $scan = $scan->fresh();
        } else {
            $scan = $scanService->advance($scan, $request->user());
        }

        return $this->jsonNoStore([
            'message' => 'Status AI skena je osvježen.',
            'data' => $this->buildScanStatusPayload($scanService, $scan),
        ]);
    }

    public function retry(Request $request, OrderAiScan $scan, OrderAiScanService $scanService): JsonResponse
    {
        $this->authorizeModuleAccess($request);
        $this->authorizeScan($request, $scan);

        if (!$scanService->canRescan($scan)) {
            return $this->jsonNoStore(
                $this->buildScanResponsePayload(
                    $scanService,
                    $scan->fresh() ?? $scan,
                    'Ponovno AI skeniranje je dostupno samo za neuspješne AI scanove.'
                ),
                422
            );
        }

        $retriedScan = $scanService->rescan(
            $scan,
            $request->user(),
            (string) ($scan->source_origin ?? 'manual') === 'imap',
            false
        );
        $retriedScan = $retriedScan->fresh() ?? $retriedScan;

        if ((string) ($retriedScan->status ?? '') === 'failed') {
            return $this->jsonNoStore(
                $this->buildScanResponsePayload(
                    $scanService,
                    $retriedScan,
                    trim((string) ($retriedScan->error_message ?? '')) !== ''
                        ? (string) $retriedScan->error_message
                        : 'AI skeniranje nije uspjelo ni nakon ponovnog pokretanja.'
                ),
                422
            );
        }

        return $this->jsonNoStore(
            $this->buildScanResponsePayload(
                $scanService,
                $retriedScan,
                (string) ($retriedScan->source_origin ?? 'manual') === 'imap'
                    ? 'AI skeniranje je ponovo pokrenuto. Status će biti osvježen automatski.'
                    : 'AI skeniranje je uspješno ponovo pokrenuto.'
            )
        );
    }

    public function source(Request $request, OrderAiScan $scan): StreamedResponse
    {
        $this->authorizeModuleAccess($request);
        $this->authorizeScan($request, $scan);

        return $this->buildSourceFileResponse($scan, false);
    }

    public function downloadSource(Request $request, OrderAiScan $scan): StreamedResponse
    {
        $this->authorizeModuleAccess($request);
        $this->authorizeScan($request, $scan);

        return $this->buildSourceFileResponse($scan, true);
    }

    private function authorizeScan(Request $request, OrderAiScan $scan): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($this->userCanAccessAiOrderModule($user)) {
            return;
        }

        if ((int) ($scan->user_id ?? 0) !== (int) ($user->id ?? 0)) {
            abort(403);
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

    private function buildScanStatusPayload(OrderAiScanService $scanService, OrderAiScan $scan): array
    {
        $payload = $scanService->buildStatusPayload($scan);
        $sourceFilePath = trim((string) ($scan->source_file_path ?? ''));

        $payload['source_document_view_url'] = $sourceFilePath !== ''
            ? route('app-order-ai-scan-source', ['scan' => $scan->id])
            : null;
        $payload['source_document_download_url'] = $sourceFilePath !== ''
            ? route('app-order-ai-scan-source-download', ['scan' => $scan->id])
            : null;
        $payload['ai_token_navbar_count'] = app(AiTokenNavbarCounter::class)->currentMonthTotal();

        return $payload;
    }

    private function buildScanResponsePayload(OrderAiScanService $scanService, OrderAiScan $scan, string $message): array
    {
        return [
            'message' => $message,
            'scan_id' => $scan->id,
            'status' => $scan->status,
            'status_url' => route('app-order-ai-scan-status', ['scan' => $scan->id]),
            'data' => $this->buildScanStatusPayload($scanService, $scan),
        ];
    }

    private function buildSourceFileResponse(OrderAiScan $scan, bool $download): StreamedResponse
    {
        $disk = Storage::disk((string) config('ai-order-scan.storage_disk', 'local'));
        $sourceFilePath = trim((string) ($scan->source_file_path ?? ''), '/');

        if ($sourceFilePath === '' || !$disk->exists($sourceFilePath)) {
            abort(404);
        }

        $downloadName = trim((string) ($scan->source_file_name ?? ''));
        $downloadName = $downloadName !== '' ? $downloadName : ('ai-scan-' . (int) $scan->id . '.pdf');
        $mimeType = trim((string) ($scan->source_mime_type ?? ''));
        $headers = [];

        if ($mimeType !== '') {
            $headers['Content-Type'] = $mimeType;
        }

        return $download
            ? $disk->download($sourceFilePath, $downloadName, $headers)
            : $disk->response($sourceFilePath, $downloadName, $headers);
    }

    private function jsonNoStore(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
