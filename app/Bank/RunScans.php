<?php

namespace App\Bank;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Writes each pinned original's real scanner result before parsing starts. */
class RunScans
{
    /** @param  iterable<DocumentVersion>  $versions */
    public function persist(int $organizationId, iterable $versions, bool $demoFile): void
    {
        foreach ($versions as $version) {
            $tmp = storage_path('app/bank-work/scan-'.Str::uuid());
            if (! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
                continue;
            }
            $path = $tmp.'/original';
            try {
                $source = Storage::disk($version->disk)->readStream($version->storage_key);
                if ($source === false) {
                    continue;
                }
                $dest = fopen($path, 'xb');
                stream_copy_to_stream($source, $dest, config('bank.max_bytes') + 1);
                fclose($source);
                fclose($dest);
                if (! is_file($path) || filesize($path) > config('bank.max_bytes')) {
                    continue;
                }
                $fictional = $demoFile && in_array($version->validation_status, ['accepted_sample', 'fictional'], true);
                $scan = app(Scanner::class)->scan($path, $fictional);
                DB::table('bank_scans')->insert([
                    'organization_id' => $organizationId,
                    'document_version_id' => $version->id,
                    'sha256' => $version->sha256,
                    'status' => $scan['status'],
                    'adapter' => $scan['adapter'],
                    'data' => Ledger::json($scan),
                    'created_at' => now(),
                ]);
                DB::table('document_versions')->where('id', $version->id)->where('organization_id', $organizationId)->where('scan_status', '!=', $scan['status'])->update(['scan_status' => $scan['status']]);
            } catch (\Throwable) {
                // One unreadable original must not stop the others from recording a scan.
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
                if (is_dir($tmp)) {
                    rmdir($tmp);
                }
            }
        }
    }
}
