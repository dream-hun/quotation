<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Concerns\ProcurementGuard;
use App\Models\ApprovalCase;
use App\Models\Award;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ReadProcurementTrends
{
    public const int WEEKS = 12;

    /** @return array{weeks: list<string>, published: list<int>, approvals: list<int>, awards: list<int>} */
    public function handle(User $actor): array
    {
        $visible = ProcurementAccess::requests($actor)->select('purchase_requests.id');
        $end = ProcurementGuard::clock()->endOfDay();
        $start = $end->subWeeks(self::WEEKS)->addSecond();

        return [
            'weeks' => array_map(fn (int $week): string => $start->addWeeks($week)->toDateString(), range(0, self::WEEKS - 1)),
            'published' => $this->buckets($start, ProcurementAccess::requests($actor)->whereBetween('published_at', [$start, $end])->pluck('published_at')),
            'approvals' => $this->buckets($start, ApprovalCase::query()->whereBetween('requested_at', [$start, $end])
                ->whereHas('round', fn (Builder $query) => $query->whereIn('request_id', clone $visible))->pluck('requested_at')),
            'awards' => $this->buckets($start, Award::query()->whereBetween('awarded_at', [$start, $end])
                ->whereIn('request_id', clone $visible)->pluck('awarded_at')),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $moments
     * @return list<int>
     */
    private function buckets(CarbonImmutable $start, Collection $moments): array
    {
        $counts = array_fill(0, self::WEEKS, 0);
        foreach ($moments as $moment) {
            if (! $moment instanceof DateTimeInterface) {
                continue;
            }

            $week = min(self::WEEKS - 1, intdiv((int) $start->diffInDays($moment), 7));
            $counts[$week]++;
        }

        return array_values($counts);
    }
}
