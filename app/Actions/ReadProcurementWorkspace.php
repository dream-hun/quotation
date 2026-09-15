<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementAccess;
use App\Models\ClarificationThread;
use App\Models\EvaluationCandidate;
use App\Models\PurchaseRequest;
use App\Models\QuotationRevision;
use App\Models\User;

final readonly class ReadProcurementWorkspace
{
    public function __construct(private ReadProcurementOptions $options) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, PurchaseRequest $request): array
    {
        ProcurementAccess::view($actor, $request);
        $supplierId = ProcurementAccess::supplierId($actor);
        $buyer = ProcurementAccess::role($actor, 'buyer');
        $commercial = ProcurementAccess::commercial($actor, $request);
        $request->load(['items', 'criteria', 'assignedApprover:id,uuid,name', 'attachmentLinks.attachment']);
        $invitations = $request->invitations()->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->with(['supplier', 'quotation.latestSubmittedRevision'])->get();
        $participation = $invitations->map(fn ($invitation): array => [
            'uuid' => $invitation->uuid, 'lock_version' => $invitation->lock_version,
            'supplier_uuid' => $invitation->supplier->uuid, 'supplier_name' => $invitation->supplier->legal_name,
            'status' => $invitation->quotation?->withdrawn_at !== null ? 'withdrawn' : ($invitation->quotation?->latestSubmittedRevision !== null ? 'responded' : ($invitation->declined_at !== null ? 'declined' : ($invitation->first_viewed_at !== null ? 'viewed' : 'invited'))),
            'quotation_version' => $invitation->quotation === null ? 0 : $invitation->quotation->lock_version,
        ]);
        if ($supplierId !== null) {
            $invitation = $invitations->first();
            if ($invitation !== null && $invitation->first_viewed_at === null) {
                $invitation->update(['first_viewed_at' => now()]);
            }
        }

        $latestIds = $invitations->map(fn ($invitation) => $invitation->quotation?->latestSubmittedRevision?->id)->filter()->all();
        $revisions = QuotationRevision::query()
            ->whereHas('quotation.invitation', fn ($query) => $query->where('request_id', $request->id)
                ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId)))
            ->when($supplierId === null, fn ($query) => $query->whereIn('id', $commercial ? $latestIds : []))
            ->with(['lines.requestItem', 'attachmentLinks.attachment'])->orderByDesc('revision_number')->get();
        $revisionData = $revisions->map(fn ($revision): array => $revision->only(['uuid', 'revision_number', 'status', 'supplier_reference', 'supplier_name_snapshot', 'currency_code', 'valid_until', 'delivery_days', 'payment_terms', 'advance_percent', 'balance_due_days', 'delivery_charge', 'total_amount', 'submitted_at', 'submission_key', 'lock_version']) + [
            'lines' => $revision->lines->map(fn ($line): array => $line->only(['uuid', 'unit_price', 'discount_amount', 'tax_amount', 'line_net', 'line_total', 'offered_description', 'supplier_compliance_response', 'zero_price_reason']) + ['item_uuid' => $line->requestItem->uuid]),
            'attachments' => $revision->attachmentLinks->map(fn ($link) => $link->attachment->only(['uuid', 'original_name', 'scan_status'])),
        ]);
        $rounds = $request->evaluationRounds()->when(! $commercial, fn ($query) => $query->whereRaw('1 = 0'))->with(['candidates.quotationRevision', 'candidates.lineAssessments.quotationLine', 'candidates.criterionAssessments.criterion', 'approvalCase'])->orderByDesc('round_number')->get();
        $approver = $rounds->isNotEmpty() && ProcurementAccess::role($actor, 'approver');
        $threads = $request->clarificationThreads()->when($supplierId !== null, fn ($query) => $query->where(fn ($query) => $query->where('visibility', 'all_invited')->orWhere('supplier_id', $supplierId)))
            ->with(['messages.author:id,name', 'messages.attachmentLinks.attachment'])->orderByDesc('id')->limit(50)->get();

        return [
            'request' => $request->only(['uuid', 'request_number', 'title', 'description', 'delivery_destination', 'currency_code', 'currency_precision', 'deadline_at', 'timezone', 'status', 'lock_version', 'cancellation_reason']) + [
                'approver_uuid' => $request->assignedApprover?->uuid, 'approver_name' => $request->assignedApprover?->name,
                'items' => $request->items->map(fn ($item) => $item->only(['uuid', 'line_number', 'description', 'specification', 'quantity', 'unit'])),
                'criteria' => $request->criteria->map(fn ($criterion) => $criterion->only(['uuid', 'name', 'category', 'mandatory', 'assessment_rule'])),
            ],
            'participation' => $participation, 'revisions' => $revisionData,
            'rounds' => $rounds->map(fn ($round): array => $round->only(['uuid', 'round_number', 'status', 'recommendation_reason', 'participation_justification', 'lock_version', 'frozen_snapshot']) + [
                'recommended_candidate_uuid' => $round->candidates->firstWhere('id', $round->recommended_candidate_id)?->uuid,
                'canAssess' => $buyer && $round->prepared_by === $actor->id && $round->status === 'draft',
                'canFreeze' => $buyer && $round->prepared_by === $actor->id && $round->status === 'draft',
                'approval' => $round->approvalCase?->only(['uuid', 'status', 'decision_reason', 'decided_at', 'lock_version']),
                'canDecide' => $approver && $round->approvalCase?->assigned_approver_id === $actor->id && $request->created_by !== $actor->id && $round->prepared_by !== $actor->id,
                'candidates' => $round->candidates->map($this->candidateData(...)),
            ]),
            'threads' => $threads->map(fn (ClarificationThread $thread): array => $this->threadData($thread, $actor)),
            'attachments' => $request->attachmentLinks->map(fn ($link) => $link->attachment->only(['uuid', 'original_name', 'scan_status'])),
            'audit' => $supplierId === null ? $request->auditEvents()->orderByDesc('id')->paginate(25, ['uuid', 'action', 'actor_label', 'occurred_at', 'safe_details'], 'audit_page') : null,
            'can' => ['buyer' => $buyer, 'supplier' => $supplierId !== null, 'commercial' => $commercial, 'internal' => ProcurementAccess::internal($actor)],
            ...$this->options->handle($buyer, $request->created_by),
        ];
    }

    /** @return array<string, mixed> */
    private function candidateData(EvaluationCandidate $candidate): array
    {
        $lines = [];
        foreach ($candidate->lineAssessments as $finding) {
            $lines[] = ['uuid' => $finding->quotationLine->uuid, 'result' => $finding->result, 'finding' => $finding->evidence_note];
        }

        $criteria = [];
        foreach ($candidate->criterionAssessments as $finding) {
            $criteria[] = ['uuid' => $finding->criterion->uuid, 'result' => $finding->result, 'finding' => $finding->finding];
        }

        return [
            'uuid' => $candidate->uuid, 'disposition' => $candidate->disposition,
            'disposition_reason' => $candidate->disposition_reason, 'lock_version' => $candidate->lock_version,
            'revision_uuid' => $candidate->quotationRevision->uuid, 'lines' => $lines, 'criteria' => $criteria,
        ];
    }

    /** @return array<string, mixed> */
    private function threadData(ClarificationThread $thread, User $actor): array
    {
        $messages = [];
        foreach ($thread->messages as $message) {
            $files = [];
            foreach ($message->attachmentLinks as $link) {
                $files[] = $link->attachment->only(['uuid', 'original_name', 'scan_status']);
            }

            $messages[] = ['uuid' => $message->uuid, 'body' => $message->body, 'author' => $message->author->name,
                'created_at' => $message->created_at, 'canAttach' => $message->author_id === $actor->id && $thread->status === 'open', 'attachments' => $files];
        }

        return [
            'uuid' => $thread->uuid, 'subject' => $thread->subject, 'visibility' => $thread->visibility,
            'status' => $thread->status, 'material' => $thread->material, 'lock_version' => $thread->lock_version,
            'messages' => $messages,
        ];
    }
}
