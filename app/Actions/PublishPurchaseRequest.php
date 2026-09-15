<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\Currency;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

final readonly class PublishPurchaseRequest
{
    public function __construct(private RecordProcurementEvent $audit, private NotifyProcurementUsers $notify) {}

    /** @param list<string> $supplierUuids */
    public function handle(User $actor, PurchaseRequest $request, int $version, array $supplierUuids): void
    {
        ProcurementAccess::buyer($actor);
        DB::transaction(function () use ($actor, $request, $version, $supplierUuids): void {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            ProcurementGuard::version($request, $version);
            ProcurementGuard::require(in_array($request->status, ['draft', 'open'], true), 'This request cannot be published or invited.');
            $currency = Currency::query()->where('code', $request->currency_code)->where('active', true)->first();
            ProcurementGuard::require($currency !== null && filled($request->title) && filled($request->delivery_destination)
                && $request->deadline_at?->isFuture(), 'Complete the title, destination, supported currency and future deadline.');
            ProcurementGuard::approver($request);
            $request->load(['items', 'criteria']);
            ProcurementGuard::require($request->items->isNotEmpty()
                && $request->items->every(fn ($item): bool => filled($item->specification) && filled($item->description) && filled($item->unit)),
                'Add at least one item with its specifications.');
            ProcurementGuard::require($request->criteria->pluck('category')->unique()->count() === 4
                && $request->criteria->every(fn ($criterion): bool => filled($criterion->assessment_rule)),
                'Define assessment rules for price, delivery, payment and specification.');
            $suppliers = Supplier::query()->whereIn('uuid', $supplierUuids)->where('status', 'active')->get();
            ProcurementGuard::require($suppliers->isNotEmpty() && $suppliers->count() === count(array_unique($supplierUuids)), 'Select active suppliers.', 'supplier_uuids');
            if ($request->status === 'open') {
                ProcurementGuard::open($request);
            } else {
                $request->currency_precision = $currency->minor_digits;
                $request->published_at = now();
                $request->status = 'open';
            }

            $request->lock_version++;
            $request->save();
            $suppliers->load(['members' => fn (Relation $query) => $query->where('status', 'active')->select(['id', 'supplier_id', 'user_id'])]);
            foreach ($suppliers as $supplier) {
                $invitation = $request->invitations()->firstOrCreate(['supplier_id' => $supplier->id],
                    ['invited_by' => $actor->id, 'invited_at' => now()]);
                $recipients = $supplier->members->pluck('user_id')->all();
                $this->notify->handle($request, $recipients, 'invitation.received', 'invitation:'.$invitation->uuid);
            }

            $this->audit->handle($actor, $request, 'request.published_or_invited', $request);
        });
    }
}
