<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CloseExpiredRequests;
use App\Actions\DeliverProcurementMail;
use App\Actions\ScanProcurementFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Description('Close expired requests, scan quarantined files, and deliver notification outbox entries')]
#[Signature('procurement:process {--health : Check database, worker heartbeat and scanner configuration}')]
final class ProcessProcurement extends Command
{
    public function handle(CloseExpiredRequests $close, ScanProcurementFiles $scan, DeliverProcurementMail $mail): int
    {
        if ($this->option('health')) {
            DB::select('SELECT 1');
            $ready = Cache::has('procurement.worker_heartbeat') && filled(config('procurement.scanner_binary'));
            $this->info($ready ? 'Procurement services are ready.' : 'Worker heartbeat or scanner configuration is missing.');

            return $ready ? self::SUCCESS : self::FAILURE;
        }

        $closed = $close->handle();
        $scanned = $scan->handle();
        $sent = $mail->handle();
        Cache::put('procurement.worker_heartbeat', now()->toISOString(), now()->addMinutes(10));
        $this->info("Closed: $closed; scanned: $scanned; mail sent: $sent.");

        return self::SUCCESS;
    }
}
