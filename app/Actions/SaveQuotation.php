<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Concerns\QuotationMoney;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** @phpstan-type Input array{lock_version: int, supplier_reference?: ?string, valid_until?: ?string, delivery_days?: ?int, payment_terms?: ?string, advance_percent?: ?string, balance_due_days?: ?int, delivery_charge?: ?string, lines: list<array{item_uuid: string, offered_description?: ?string, unit_price?: ?string, discount_amount?: ?string, tax_amount?: ?string, supplier_compliance_response?: ?string, zero_price_reason?: ?string}>} */
final readonly class SaveQuotation
{
    public function __construct(private RecordProcurementEvent $audit) {}

    /** @param Input $data */
    public function handle(User $actor, PurchaseRequest $request, array $data): QuotationRevision
    {
        return DB::transaction(function () use ($actor, $request, $data): QuotationRevision {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            $supplierId = ProcurementAccess::supplier($actor, $request);
            ProcurementGuard::open($request);
            $invitation = $request->invitations()->where('supplier_id', $supplierId)->firstOrFail();
            ProcurementGuard::require($invitation->declined_at === null, 'Reverse your decline before preparing a quotation.');
            $quotation = Quotation::query()->firstOrCreate(['invitation_id' => $invitation->id]);
            $draft = $quotation->revisions()->where('status', 'draft')->lockForUpdate()->first();
            if ($draft !== null) {
                ProcurementGuard::version($draft, (int) $data['lock_version']);
            } else {
                ProcurementGuard::require((int) $data['lock_version'] === 0, 'Reload before starting a new revision.', 'lock_version');
                $draft = $quotation->revisions()->create([
                    'revision_number' => ($quotation->revisions()->count()) + 1,
                    'status' => 'draft', 'prepared_by' => $actor->id, 'lock_version' => 1,
                ]);
            }

            $draft->fill(['supplier_reference' => $data['supplier_reference'] ?? null, 'valid_until' => $data['valid_until'] ?? null, 'delivery_days' => $data['delivery_days'] ?? null, 'payment_terms' => $data['payment_terms'] ?? null, 'advance_percent' => $data['advance_percent'] ?? null, 'balance_due_days' => $data['balance_due_days'] ?? null, 'delivery_charge' => $data['delivery_charge'] ?? null]);
            $draft->currency_code = $request->currency_code;
            $draft->lock_version++;
            $draft->save();
            $items = $request->items()->get()->keyBy('uuid');
            foreach ($data['lines'] as $index => $line) {
                $item = $items->get($line['item_uuid']);
                ProcurementGuard::require($item !== null, 'This item does not belong to this request.', "lines.$index.item_uuid");
                if (isset($line['unit_price'])) {
                    QuotationMoney::line($item->quantity, $line['unit_price'], $line['discount_amount'] ?? '0', $line['tax_amount'] ?? '0', $request->currency_precision ?? 4);
                }

                $draft->lines()->updateOrCreate(['request_item_id' => $item->id],
                    ['offered_description' => $line['offered_description'] ?? null, 'unit_price' => $line['unit_price'] ?? null, 'discount_amount' => $line['discount_amount'] ?? '0', 'tax_amount' => $line['tax_amount'] ?? '0', 'supplier_compliance_response' => $line['supplier_compliance_response'] ?? null, 'zero_price_reason' => $line['zero_price_reason'] ?? null]);
            }

            $this->audit->handle($actor, $request, 'quotation.draft_saved', $draft);

            return $draft;
        });
    }
}
