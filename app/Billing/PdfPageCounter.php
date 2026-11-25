<?php

namespace App\Billing;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Best-effort page count used before a full parse. Images count as one page. */
class PdfPageCounter
{
    public function count(string $bytes, ?string $mime = null): int
    {
        if ($mime !== null && str_starts_with($mime, 'image/')) {
            return 1;
        }
        if ($bytes === '' || ! str_starts_with($bytes, '%PDF')) {
            return 1;
        }
        $pages = preg_match_all('/\/Type\s*\/Page(?![A-Za-z])/', $bytes);
        if (is_int($pages) && $pages > 0) {
            return $pages;
        }
        if (preg_match_all('/\/Count\s+(\d+)/', $bytes, $matches) && $matches[1] !== []) {
            return max(1, max(array_map('intval', $matches[1])));
        }

        return 1;
    }

    public function countVersion(DocumentVersion $version): int
    {
        try {
            $disk = Storage::disk($version->disk);
            if (! $disk->exists($version->storage_key)) {
                return 1;
            }
            $bytes = $disk->get($version->storage_key);

            return $this->count(is_string($bytes) ? $bytes : '', $version->mime);
        } catch (Throwable) {
            return 1;
        }
    }
}
