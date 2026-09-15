<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @phpstan-type Input array{lock_version: int, title: string, description?: ?string, delivery_destination?: ?string, currency_code?: ?string, deadline_at?: ?string, approver_uuid?: ?string, items: list<array{description: string, specification?: ?string, quantity: numeric-string, unit: string}>, criteria: list<array{name: string, category: string, mandatory: bool, assessment_rule?: ?string}>} */
final readonly class SavePurchaseRequest
{
    public function __construct(private RecordProcurementEvent $audit) {}

    /** @param Input $data */
    public function handle(User $actor, array $data, ?PurchaseRequest $request = null): PurchaseRequest
    {
        ProcurementAccess::buyer($actor);

        return DB::transaction(function () use ($actor, $data, $request): PurchaseRequest {
            $organization = Organization::query()->first();
            ProcurementGuard::require($organization !== null, 'An administrator must configure the buying organization first.');
            if ($request instanceof PurchaseRequest) {
                $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
                ProcurementGuard::version($request, (int) $data['lock_version']);
                ProcurementGuard::require($request->status === 'draft', 'Published requirements cannot be changed.');
            } else {
                $request = new PurchaseRequest([
                    'organization_id' => $organization->id, 'created_by' => $actor->id,
                    'request_number' => 'RFQ-'.Str::upper((string) Str::ulid()),
                    'timezone' => $organization->timezone, 'status' => 'draft', 'lock_version' => 0,
                ]);
            }

            $fields = ['title' => $data['title'], 'description' => $data['description'] ?? null, 'delivery_destination' => $data['delivery_destination'] ?? null, 'currency_code' => $data['currency_code'] ?? null, 'deadline_at' => $data['deadline_at'] ?? null];
            $fields['assigned_approver_id'] = empty($data['approver_uuid']) ? null : User::query()->where('uuid', $data['approver_uuid'])->value('id');
            ProcurementGuard::require($fields['assigned_approver_id'] !== $request->created_by, 'The creator cannot approve this request.', 'approver_uuid');
            $request->fill($fields);
            $request->lock_version++;
            $request->save();
            $request->items()->delete();
            $request->criteria()->delete();
            foreach ($data['items'] as $index => $item) {
                $request->items()->create(['description' => $item['description'], 'specification' => $item['specification'] ?? '', 'quantity' => $item['quantity'], 'unit' => $item['unit'], 'line_number' => $index + 1]);
            }

            foreach ($data['criteria'] as $index => $criterion) {
                $request->criteria()->create(['name' => $criterion['name'], 'category' => $criterion['category'], 'mandatory' => $criterion['mandatory'], 'assessment_rule' => $criterion['assessment_rule'] ?? '', 'display_order' => $index + 1]);
            }

            $this->audit->handle($actor, $request, 'request.saved', $request);

            return $request;
        });
    }
}
