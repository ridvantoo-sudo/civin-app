<?php

namespace App\Features\Operations\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class StorageCheckCommand extends Command
{
    protected $signature = 'ops:storage-check {--disk= : Filesystem disk to verify}';

    protected $description = 'Verify that object storage supports write, read, URL, and delete operations';

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('operations.storage_disk'));
        $disk = Storage::disk($diskName);
        $path = 'ops/probes/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(6)).'.txt';
        $contents = 'civin-storage-probe:'.now()->toIso8601String();

        try {
            if (! $disk->put($path, $contents)) {
                throw new \RuntimeException('Write returned false.');
            }

            if ($disk->get($path) !== $contents) {
                throw new \RuntimeException('Read-back did not match the probe.');
            }

            $url = $disk->url($path);
            if ($url === '') {
                throw new \RuntimeException('The disk generated an empty URL.');
            }

            $disk->delete($path);
            if ($disk->exists($path)) {
                throw new \RuntimeException('Probe cleanup failed.');
            }
        } catch (\Throwable $exception) {
            try {
                $disk->delete($path);
            } catch (\Throwable) {
                // Preserve the original storage failure.
            }

            $this->error("Storage check failed for [{$diskName}]: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Storage check passed for [{$diskName}]. URL: {$url}");

        return self::SUCCESS;
    }
}
