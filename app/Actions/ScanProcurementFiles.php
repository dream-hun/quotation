<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

final readonly class ScanProcurementFiles
{
    public function handle(): int
    {
        $scanner = config('procurement.scanner_binary');
        if (! is_string($scanner) || $scanner === '') {
            return 0;
        }

        $count = 0;
        foreach (Attachment::query()->where('scan_status', 'pending')->orderBy('id')->limit(10)->get() as $attachment) {
            $path = Storage::disk('local')->path($attachment->storage_key);
            if (! is_file($path) || hash_file('sha256', $path) !== $attachment->content_hash) {
                $attachment->update(['scan_status' => 'rejected', 'scanned_at' => now()]);

                continue;
            }

            $process = new Process($this->command($scanner, $path), timeout: 30);
            try {
                $exit = $process->run();
                if (in_array($exit, [0, 1], true)) {
                    $attachment->update(['scan_status' => $exit === 0 ? 'clean' : 'rejected', 'scanned_at' => now()]);
                    $count++;
                } else {
                    Log::warning('Procurement file scan failed; it will be retried.', ['attachment' => $attachment->uuid, 'exit_code' => $exit, 'output' => mb_substr(mb_trim($process->getOutput().$process->getErrorOutput()), 0, 500)]);
                }
            } catch (Throwable $throwable) {
                Log::warning('Procurement file scan failed; it will be retried.', ['attachment' => $attachment->uuid, 'error' => $throwable->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * clamdscan hands the file to the clamd daemon, which runs as another user and cannot
     * open private storage. --fdpass makes clamdscan open the file and pass the descriptor.
     *
     * @return list<string>
     */
    private function command(string $scanner, string $path): array
    {
        return basename($scanner) === 'clamdscan'
            ? [$scanner, '--fdpass', '--no-summary', $path]
            : [$scanner, '--no-summary', $path];
    }
}
