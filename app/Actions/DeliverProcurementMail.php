<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Models\ProcurementNotification;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final readonly class DeliverProcurementMail
{
    public function handle(): int
    {
        $sent = 0;
        $ids = ProcurementNotification::query()->where('email_attempts', '<', 5)
            ->where(function ($query): void {
                $query->where(fn ($query) => $query->whereIn('email_status', ['pending', 'failed'])->where('next_attempt_at', '<=', now()))
                    ->orWhere(fn ($query) => $query->where('email_status', 'sending')->where('claimed_until', '<', now()));
            })->orderBy('id')->limit(50)->pluck('id');
        foreach ($ids as $id) {
            $notice = DB::transaction(function () use ($id): ?ProcurementNotification {
                $notice = ProcurementNotification::query()->where('id', $id)->lockForUpdate()->firstOrFail();
                if ($notice->email_attempts >= 5 || ! in_array($notice->email_status, ['pending', 'failed', 'sending'], true)
                    || ($notice->next_attempt_at !== null && $notice->next_attempt_at->isFuture())
                    || ($notice->claimed_until !== null && $notice->claimed_until->isFuture())) {
                    return null;
                }

                $notice->update(['email_status' => 'sending', 'claimed_until' => now()->addMinutes(5), 'email_attempts' => $notice->email_attempts + 1]);

                return $notice;
            });
            if ($notice === null) {
                continue;
            }

            $notice->load(['recipient', 'request']);
            if ($notice->recipient->status !== 'active' || $notice->request === null
                || ! ProcurementAccess::requests($notice->recipient)->whereKey($notice->request_id)->exists()) {
                $notice->update(['email_status' => 'not_required', 'claimed_until' => null]);

                continue;
            }

            try {
                $link = route('procurement.show', $notice->request);
                Mail::raw("There is a procurement update in your account.\nSign in to review it: ".$link, function (Message $message) use ($notice): void {
                    $message->to($notice->recipient->email)->subject('Procurement account update');
                });
                $notice->update(['email_status' => 'sent', 'email_sent_at' => now(), 'claimed_until' => null, 'last_error_code' => null]);
                $sent++;
            } catch (Throwable) {
                $notice->update(['email_status' => 'failed', 'claimed_until' => null, 'last_error_code' => 'transport_failure',
                    'next_attempt_at' => now()->addMinutes(2 ** $notice->email_attempts)]);
            }
        }

        return $sent;
    }
}
