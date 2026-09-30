<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Enums\ProcurementPermission;
use App\Models\Organization;
use App\Models\User;

/** @phpstan-type Input array{q?: ?string, supplier?: ?string, status?: ?string, from?: ?string, to?: ?string} */
final readonly class ReadProcurementIndex
{
    public function __construct(private ReadProcurementOptions $options, private ReadProcurementTasks $tasks) {}

    /** @param Input $filters
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $filters): array
    {
        $query = ProcurementAccess::requests($actor);
        $visibleRequests = clone $query;
        $counts = (clone $query)->selectRaw('status, count(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $query->when($filters['q'] ?? null, fn ($query, $value) => $query->where(function ($query) use ($value): void {
            $query->where('title', 'like', '%'.$value.'%')->orWhere('request_number', 'like', '%'.$value.'%');
        }))->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
        if (ProcurementAccess::internal($actor)) {
            $query->when($filters['supplier'] ?? null, fn ($query, $supplier) => $query->whereHas('invitations.supplier',
                fn ($query) => $query->where('legal_name', 'like', '%'.$supplier.'%')));
        }

        $requests = $query->select(['id', 'uuid', 'request_number', 'title', 'status', 'deadline_at', 'timezone', 'currency_code', 'created_at', 'assigned_approver_id'])->latest()->orderByDesc('id')->paginate(20)->withQueryString();
        $buyer = ProcurementAccess::can($actor, ProcurementPermission::ManageRequests);
        $notifications = $actor->procurementNotifications()->whereHas('request', fn ($query) => $query->whereIn('id', $visibleRequests->select('id')))
            ->with('request:id,uuid')->orderByDesc('id')->limit(15)->get()->map(fn ($notification): array => [
                'uuid' => $notification->uuid, 'event_type' => $notification->event_type,
                'request_uuid' => $notification->request?->uuid, 'created_at' => $notification->created_at?->toISOString(),
            ]);

        return [
            'requests' => $requests, 'counts' => $counts, 'filters' => $filters, 'notifications' => $notifications,
            'canCreate' => $buyer, 'isAdministrator' => ProcurementAccess::can($actor, ProcurementPermission::ManageAccess),
            'isSupplier' => ProcurementAccess::supplierId($actor) !== null,
            'organization' => Organization::query()->first()?->only(['name', 'timezone', 'default_currency_code']),
            ...$this->options->handle($buyer, $actor->id),
            ...$this->tasks->handle($actor),
        ];
    }
}
