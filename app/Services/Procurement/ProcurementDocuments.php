<?php

namespace App\Services\Procurement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcurementDocuments
{
    public function upload(string $id, UploadedFile $file, array $data, int $userId): array
    {
        $service = app(ProcurementService::class);
        $service->find($id);
        $path = $file->storeAs('procurement-documents/' . hash('sha256', app(ProcurementStore::class)->directory()), (string) Str::uuid() . '.' . $file->extension(), 'local');
        if (!$path) throw new \RuntimeException('Dokument nije sačuvan.');
        try {
            return $service->addDocument($id, ['path'=>$path,'name'=>basename($file->getClientOriginalName()),'mime'=>$file->getMimeType(),
                'size'=>$file->getSize(),'type'=>$data['type'],'line_id'=>$data['line_id'] ?? '', 'complaint_id'=>$data['complaint_id'] ?? '', 'receipt_id'=>$data['receipt_id'] ?? '', 'shared'=>!empty($data['shared'])], $userId);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function download(array $purchase, string $documentId, bool $supplier = false)
    {
        foreach ($purchase['documents'] ?? [] as $document) {
            if ($document['id'] !== $documentId || ($supplier && empty($document['shared']))) continue;
            abort_unless(Storage::disk('local')->exists($document['path']), 404);
            return Storage::disk('local')->download($document['path'], $document['name'], ['X-Content-Type-Options'=>'nosniff']);
        }
        abort(404);
    }

    public static function rules(): array
    {
        return ['file'=>'required|file|mimes:pdf,jpg,jpeg,png,docx|max:10240','type'=>'required|in:invoice,certificate,complaint,other','line_id'=>'nullable|uuid','complaint_id'=>'nullable|uuid','receipt_id'=>'nullable|uuid','shared'=>'nullable|boolean'];
    }
}
