<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Readers\XlsWorkbookSource;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\WorkbookSourceFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Generated, local and non-public. No original filename ever participates in a path. */
final class PrivateWorkbookStorage
{
    private function disk(): FilesystemAdapter
    {
        return Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports'), 'visibility' => 'private', 'throw' => true]);
    }

    public function store(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new ImportConflict('invalid_upload');
        }
        $format = strtolower($file->getClientOriginalExtension());
        if (! in_array($format, ['xls', 'xlsx', 'csv'], true)) {
            throw new ImportConflict('unsupported_upload_type');
        }
        $budget = new AnalysisBudget;
        $bytes = $file->getSize();
        $budget->guard('file_bytes', $bytes);
        if ($bytes < 1) {
            throw new ImportConflict('empty_upload');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $allowed = match ($format) {
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
            'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
        };
        if (! in_array($mime, $allowed, true)) {
            throw new ImportConflict('upload_content_type_mismatch');
        }
        $source = $format === 'xls'
            ? new XlsWorkbookSource($file->getPathname(), $budget)
            : (new WorkbookSourceFactory)->open($file->getPathname(), $format, $budget);
        $source->close();
        $hash = hash_file('sha256', $file->getPathname());
        $key = (string) Str::uuid().'.'.$format;
        $stream = fopen($file->getPathname(), 'rb');
        try {
            $this->disk()->put($key, $stream);
        } finally {
            fclose($stream);
        }
        $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', str_replace('\\', '/', $file->getClientOriginalName())), 0, 200);
        $data = ['storage_key' => $key, 'original_name' => basename($name), 'format' => $format, 'mime' => $mime, 'byte_count' => $bytes, 'workbook_hash' => $hash];
        try {
            $this->path((object) $data);
        } catch (\Throwable $e) {
            $this->discardUnregistered($key);
            throw $e;
        }

        return $data;
    }

    public function path(object $artifact): string
    {
        if (! preg_match('/^[a-f0-9-]{36}\.(xls|xlsx|csv)$/D', $artifact->storage_key)) {
            throw new ImportConflict('invalid_private_storage_key');
        }
        $path = $this->disk()->path($artifact->storage_key);
        if (! is_file($path) || is_link($path) || filesize($path) !== (int) $artifact->byte_count || ! hash_equals($artifact->workbook_hash, hash_file('sha256', $path))) {
            throw new ImportConflict('workbook_integrity_error');
        }

        return $path;
    }

    /** Only compensation for a generated upload that never became a registered artifact. */
    public function discardUnregistered(string $key): void
    {
        $registeredPilot = Schema::hasTable('wald_pilot_uploads')
            && DB::table('wald_pilot_uploads')->where('storage_key', $key)->exists();
        if (preg_match('/^[a-f0-9-]{36}\.(xls|xlsx|csv)$/D', $key)
            && ! $registeredPilot
            && ! DB::table('wald_import_runs')->where('storage_key', $key)->exists()) {
            $this->disk()->delete($key);
        }
    }
}
