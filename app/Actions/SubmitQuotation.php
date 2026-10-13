<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Concerns\QuotationMoney;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SubmitQuotation
{
    public function __construct(private RecordProcurementEvent $audit, private NotifyProcurementUsers $notify) {}

    public function handle(User $actor, PurchaseRequest $request, string $revisionUuid, int $version, string $key): QuotationRevision
    {
        return DB::transaction(function () use ($actor, $request, $revisionUuid, $version, $key): QuotationRevision {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            $supplierId = ProcurementAccess::supplier($actor, $request);
            $invitation = $request->invitations()->where('supplier_id', $supplierId)->firstOrFail();
            $quotation = Quotation::query()->where('invitation_id', $invitation->id)->lockForUpdate()->firstOrFail();
            $revision = $quotation->revisions()->where('uuid', $revisionUuid)->lockForUpdate()->firstOrFail();
            if ($revision->status === 'submitted' && $revision->submission_key === $key) {
                return $revision;
            }

            ProcurementGuard::open($request);
            ProcurementGuard::require($invitation->declined_at === null, 'Reverse your decline before submitting a quotation.');
            ProcurementGuard::version($revision, $version);
            ProcurementGuard::require($revision->status === 'draft', 'This revision is already submitted.');
            ProcurementGuard::require(! QuotationRevision::query()->where('submission_key', $key)->exists(), 'This submission key was already used.');
            ProcurementGuard::require($revision->currency_code === $request->currency_code && $revision->valid_until !== null
                && $revision->valid_until->toDateString() >= now()->timezone($request->timezone)->toDateString()
                && $revision->delivery_days !== null && filled($revision->payment_terms) && $revision->delivery_charge !== null,
                'Complete currency, validity, delivery days, delivery charge and payment terms.');
            $revision->load(['lines.requestItem', 'attachments']);
            ProcurementGuard::require($revision->lines->count() === $request->items()->count(), 'Price every requested item.');
            ProcurementGuard::require(! config('procurement.require_quotation_attachment') || $revision->attachments->isNotEmpty(), 'Attach a supporting document.');
            ProcurementGuard::require($revision->delivery_charge !== null, 'Enter a delivery charge.');
            $total = QuotationMoney::amount($revision->delivery_charge, $request->currency_precision ?? 4);
            foreach ($revision->lines as $line) {
                ProcurementGuard::require($line->unit_price !== null && $line->discount_amount !== null && $line->tax_amount !== null
                    && filled($line->offered_description) && filled($line->supplier_compliance_response), 'Complete each line price, description and compliance response.');
                ProcurementGuard::require(QuotationMoney::amount($line->unit_price)->compare('0') !== 0 || filled($line->zero_price_reason), 'Explain every zero price.');
                $amounts = QuotationMoney::line($line->requestItem->quantity, $line->unit_price, $line->discount_amount, $line->tax_amount, $request->currency_precision ?? 4);
                $line->update($amounts);
                $total = $total->add($amounts['line_total']);
            }

            $supplier = Supplier::query()->findOrFail($supplierId);
            $submittedAt = ProcurementGuard::clock();
            ProcurementGuard::require($submittedAt->lessThan($request->deadline_at), 'The submission deadline has passed.');
            $revision->update([
                'status' => 'submitted', 'submitted_at' => $submittedAt, 'submitted_by' => $actor->id,
                'submission_key' => $key, 'supplier_name_snapshot' => $supplier->legal_name,
                'supplier_registration_snapshot' => $supplier->registration_number,
                'total_amount' => QuotationMoney::bounded($total), 'calculation_version' => QuotationMoney::VERSION,
                'lock_version' => $revision->lock_version + 1,
            ]);
            $quotation->update(['withdrawn_at' => null, 'withdrawn_by' => null, 'withdrawal_reason' => null, 'lock_version' => $quotation->lock_version + 1]);
            $this->audit->handle($actor, $request, 'quotation.submitted', $revision);
            $this->notify->handle($request, [$actor->id], 'quotation.receipt', 'receipt:'.$revision->uuid);

            return $revision;
        });
    }
}
