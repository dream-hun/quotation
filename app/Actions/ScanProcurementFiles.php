<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Attachment;
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

            $process = new Process([$scanner, '--no-summary', $path], timeout: 30);
            try {
                $exit = $process->run();
                if (in_array($exit, [0, 1], true)) {
                    $attachment->update(['scan_status' => $exit === 0 ? 'clean' : 'rejected', 'scanned_at' => now()]);
                    $count++;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $count;
    }
}
