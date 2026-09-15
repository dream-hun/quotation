<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementGuard;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

final readonly class CloseExpiredRequests
{
    public function __construct(private RecordProcurementEvent $audit) {}

    public function handle(): int
    {
        $count = 0;
        PurchaseRequest::query()->where('status', 'open')->where('deadline_at', '<=', now())->orderBy('id')->chunkById(100, function ($requests) use (&$count): void {
            foreach ($requests as $request) {
                DB::transaction(function () use ($request, &$count): void {
                    $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
                    if ($request->status === 'open' && $request->deadline_at !== null && ProcurementGuard::clock()->greaterThanOrEqualTo($request->deadline_at)) {
                        $request->update(['status' => 'closed', 'closed_at' => $request->deadline_at, 'lock_version' => $request->lock_version + 1]);
                        $this->audit->handle(null, $request, 'request.closed', $request);
                        $count++;
                    }
                });
            }
        });

        return $count;
    }
}
