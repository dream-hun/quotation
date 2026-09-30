<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CloseExpiredRequests;
use App\Actions\DeliverProcurementMail;
use App\Actions\PruneUploadChunks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Description('Close expired requests, deliver notification outbox entries, and prune abandoned uploads')]
#[Signature('procurement:process {--health : Check database and worker heartbeat}')]
final class ProcessProcurement extends Command
{
    public function handle(CloseExpiredRequests $close, DeliverProcurementMail $mail, PruneUploadChunks $prune): int
    {
        if ($this->option('health')) {
            DB::select('SELECT 1');
            $ready = Cache::has('procurement.worker_heartbeat');
            $this->info($ready ? 'Procurement services are ready.' : 'Worker heartbeat is missing.');

            return $ready ? self::SUCCESS : self::FAILURE;
        }

        $closed = $close->handle();
        $sent = $mail->handle();
        $prune->handle();
        Cache::put('procurement.worker_heartbeat', now()->toISOString(), now()->addMinutes(10));
        $this->info("Closed: $closed; mail sent: $sent.");

        return self::SUCCESS;
    }
}
