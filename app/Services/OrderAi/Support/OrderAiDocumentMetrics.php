<?php

namespace App\Services\OrderAi\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderAiDocumentMetrics
{
    public function resolveForUpload(UploadedFile $file): array
    {
        $pageCount = $this->resolvePageCountFromBytes(
            $this->readLocalFile((string) $file->getRealPath()),
            (string) ($file->getMimeType() ?: ''),
            (string) $file->getClientOriginalName()
        );

        return $this->buildMetrics($pageCount);
    }

    public function resolveForStoredFile(string $disk, ?string $path, ?string $mime = null, ?string $fileName = null): array
    {
        $normalizedPath = trim((string) $path);

        if ($normalizedPath === '' || !Storage::disk($disk)->exists($normalizedPath)) {
            return $this->buildMetrics(0);
        }

        $pageCount = $this->resolvePageCountFromBytes(
            (string) Storage::disk($disk)->get($normalizedPath),
            (string) ($mime ?? ''),
            (string) ($fileName ?? '')
        );

        return $this->buildMetrics($pageCount);
    }

    public function calculateBilledTokens(int $pageCount): int
    {
        if ($pageCount <= 0) {
            return 0;
        }

        return max(10, $pageCount);
    }

    private function buildMetrics(int $pageCount): array
    {
        return [
            'page_count' => max(0, $pageCount),
            'billed_tokens' => $this->calculateBilledTokens($pageCount),
        ];
    }

    private function resolvePageCountFromBytes(string $bytes, string $mime, string $fileName): int
    {
        if ($bytes === '') {
            return $this->looksLikePdf($mime, $fileName, '') ? 1 : 0;
        }

        if (!$this->looksLikePdf($mime, $fileName, $bytes)) {
            return 1;
        }

        $imagickCount = $this->resolvePageCountWithImagick($bytes);

        if ($imagickCount > 0) {
            return $imagickCount;
        }

        $typePageMatches = preg_match_all('/\/Type\s*\/Page\b/i', $bytes);

        if (is_int($typePageMatches) && $typePageMatches > 0) {
            return $typePageMatches;
        }

        if (preg_match_all('/\/Count\s+(\d+)/i', $bytes, $matches) === false) {
            return 1;
        }

        $pageCounts = array_map(static function ($value) {
            return (int) $value;
        }, $matches[1] ?? []);

        $pageCounts = array_filter($pageCounts, static function (int $value) {
            return $value > 0;
        });

        if ($pageCounts !== []) {
            return max($pageCounts);
        }

        return 1;
    }

    private function resolvePageCountWithImagick(string $bytes): int
    {
        if (!class_exists(\Imagick::class)) {
            return 0;
        }

        try {
            $imagick = new \Imagick();
            $imagick->pingImageBlob($bytes);
            $pageCount = (int) $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();

            return max(0, $pageCount);
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function looksLikePdf(string $mime, string $fileName, string $bytes): bool
    {
        $normalizedMime = Str::lower(trim($mime));
        $normalizedName = Str::lower(trim($fileName));

        if ($normalizedMime !== '' && Str::contains($normalizedMime, 'pdf')) {
            return true;
        }

        if ($normalizedName !== '' && Str::endsWith($normalizedName, '.pdf')) {
            return true;
        }

        return str_starts_with($bytes, '%PDF-');
    }

    private function readLocalFile(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $bytes = @file_get_contents($path);

        return is_string($bytes) ? $bytes : '';
    }
}
