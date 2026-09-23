<?php

declare(strict_types=1);

use App\Models\ApprovalCase;
use App\Models\AuditEvent;
use App\Models\Award;
use App\Models\ClarificationMessage;
use App\Models\ClarificationThread;
use App\Models\CriterionAssessment;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationRound;
use App\Models\Invitation;
use App\Models\LineAssessment;
use App\Models\Organization;
use App\Models\ProcurementNotification;
use App\Models\PurchaseRequest;
use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Models\RequestAttachment;
use App\Models\RequestItem;
use App\Models\RevisionAttachment;
use App\Models\SupplierMember;
use App\Models\User;
use Database\Seeders\ProcurementDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('business records resolve by UUID while retaining integer internal keys', function (): void {
    $request = PurchaseRequest::factory()->create();

    expect($request->uuid)->toBeUuid();
    expect($request->resolveRouteBinding($request->uuid)?->getKey())->toBe($request->id);
    expect($request->currency?->code)->toBe($request->currency_code);
});

test('user UUID generation preserves authentication and normalizes email identity', function (): void {
    $user = User::factory()->create(['email' => 'Buyer@Example.com']);

    expect($user->uuid)->toBeUuid();
    expect($user->refresh()->normalized_email)->toBe('buyer@example.com');
    expect(fn () => User::factory()->create(['email' => 'buyer@example.com']))->toThrow(QueryException::class);
});

test('only one buying organization is allowed', function (): void {
    Organization::factory()->create();

    expect(fn () => Organization::factory()->create())->toThrow(QueryException::class);
});

test('duplicate supplier invitations are rejected', function (): void {
    $invitation = Invitation::factory()->create();

    expect(fn () => Invitation::factory()->create([
        'request_id' => $invitation->request_id,
        'supplier_id' => $invitation->supplier_id,
    ]))->toThrow(QueryException::class);
});

test('supplier membership and internal roles cannot coexist', function (bool $membershipFirst): void {
    $user = User::factory()->create();

    if ($membershipFirst) {
        SupplierMember::factory()->create(['user_id' => $user->id]);
        expect(fn () => $user->assignRole('buyer'))->toThrow(QueryException::class);
    } else {
        $user->assignRole('buyer');
        expect(fn () => SupplierMember::factory()->create(['user_id' => $user->id]))->toThrow(QueryException::class);
    }
})->with([true, false]);

test('one user cannot represent multiple suppliers', function (): void {
    $membership = SupplierMember::factory()->create();

    expect(fn () => SupplierMember::factory()->create(['user_id' => $membership->user_id]))->toThrow(QueryException::class);
});

test('a submitted revision remains current while its replacement is a draft', function (): void {
    $submitted = QuotationRevision::factory()->submitted()->create();
    $draft = QuotationRevision::factory()->create(['quotation_id' => $submitted->quotation_id, 'revision_number' => 2]);

    expect($submitted->quotation->latestSubmittedRevision?->id)->toBe($submitted->id);
    expect(fn () => QuotationRevision::factory()->create([
        'quotation_id' => $submitted->quotation_id, 'revision_number' => 3,
    ]))->toThrow(QueryException::class);
    expect($draft->refresh()->status)->toBe('draft');
});

test('later submitted revisions replace the comparison reference without changing earlier evidence', function (): void {
    $first = QuotationRevision::factory()->submitted()->create();
    $second = QuotationRevision::factory()->submitted()->create(['quotation_id' => $first->quotation_id, 'revision_number' => 2]);

    expect($first->quotation->latestSubmittedRevision?->id)->toBe($second->id);
    expect($first->refresh()->total_amount)->toBe('299100.0000');
});

test('quote lines cannot reference another procurement request', function (): void {
    $revision = QuotationRevision::factory()->create();
    $foreignItem = RequestItem::factory()->create();

    expect(fn () => QuotationLine::factory()->create([
        'revision_id' => $revision->id, 'request_item_id' => $foreignItem->id,
    ]))->toThrow(QueryException::class, 'line_matches_request');
});

test('duplicate quotation lines are rejected', function (): void {
    $line = QuotationLine::factory()->create();

    expect(fn () => QuotationLine::factory()->create([
        'revision_id' => $line->revision_id, 'request_item_id' => $line->request_item_id,
    ]))->toThrow(QueryException::class);
});

test('submitted quotation evidence rejects direct database edits and deletion', function (string $operation): void {
    $revision = QuotationRevision::factory()->submitted()->create();
    $query = DB::table('quotation_revisions')->where('id', $revision->id);

    expect(fn () => $operation === 'update' ? $query->update(['payment_terms' => 'Altered']) : $query->delete())
        ->toThrow(QueryException::class, 'submitted_revision_is_immutable');
})->with(['update', 'delete']);

test('submitted quotation lines reject direct database edits', function (): void {
    $revision = QuotationRevision::factory()->submitted()->create();

    expect(fn () => DB::table('quotation_lines')->where('revision_id', $revision->id)->update(['unit_price' => '26000.0000']))
        ->toThrow(QueryException::class, 'submitted_revision_children_are_immutable');
});

test('submitted quotation attachment links cannot be added later', function (): void {
    $revision = QuotationRevision::factory()->submitted()->create();

    expect(fn () => RevisionAttachment::factory()->create(['revision_id' => $revision->id]))
        ->toThrow(QueryException::class, 'submitted_revision_children_are_immutable');
});

test('decimal quotation values are returned without float conversion', function (): void {
    $line = QuotationLine::factory()->create();

    expect($line->refresh()->unit_price)->toBe('25000.0000');
    expect($line->refresh()->line_total)->toBe('289100.0000');
});

test('invalid money and quantities are rejected', function (string $field, string $value): void {
    expect(fn () => QuotationLine::factory()->create([$field => $value]))->toThrow(QueryException::class);
})->with([
    'negative price' => ['unit_price', '-0.0001'],
    'negative discount' => ['discount_amount', '-1.0000'],
    'negative tax' => ['tax_amount', '-1.0000'],
    'excessive discount' => ['discount_amount', '250001.0000'],
]);

test('request item quantity must be positive', function (): void {
    expect(fn () => RequestItem::factory()->create(['quantity' => '0.0000']))
        ->toThrow(QueryException::class, 'positive_item_quantity');
});

test('published request scope is immutable but a deadline can be extended', function (): void {
    $request = PurchaseRequest::factory()->create(['published_at' => now(), 'status' => 'open']);
    $deadline = now()->addWeeks(2);
    $request->update(['deadline_at' => $deadline]);

    expect($request->refresh()->deadline_at?->toDateTimeString())->toBe($deadline->toDateTimeString());
    expect(fn () => $request->update(['title' => 'Different scope']))
        ->toThrow(QueryException::class, 'published_request_scope_is_immutable');
});

test('published request items cannot be changed', function (): void {
    $item = RequestItem::factory()->create();
    $item->request->update(['published_at' => now(), 'status' => 'open']);

    expect(fn () => $item->update(['quantity' => '20.0000']))
        ->toThrow(QueryException::class, 'published_request_children_are_immutable');
});

test('an evaluation cannot include evidence from another request', function (): void {
    $round = EvaluationRound::factory()->create();
    $revision = QuotationRevision::factory()->submitted()->create();

    expect(fn () => EvaluationCandidate::factory()->create([
        'round_id' => $round->id, 'quotation_revision_id' => $revision->id,
    ]))->toThrow(QueryException::class, 'candidate_matches_request');
});

test('an evaluation can include only one revision per quotation', function (): void {
    $candidate = EvaluationCandidate::factory()->create();
    $revision = QuotationRevision::factory()->submitted()->create([
        'quotation_id' => $candidate->quotationRevision->quotation_id,
        'revision_number' => 2,
    ]);

    expect(fn () => EvaluationCandidate::factory()->create([
        'round_id' => $candidate->round_id, 'quotation_revision_id' => $revision->id,
    ]))->toThrow(QueryException::class, 'one_revision_per_candidate_supplier');
});

test('recommendations cannot reference another round', function (): void {
    $candidate = EvaluationCandidate::factory()->create();
    $round = EvaluationRound::factory()->create();

    expect(fn () => $round->update(['recommended_candidate_id' => $candidate->id]))
        ->toThrow(QueryException::class, 'recommendation_matches_round');
});

test('one editable evaluation round is allowed per request', function (): void {
    $round = EvaluationRound::factory()->create();

    expect(fn () => EvaluationRound::factory()->create(['request_id' => $round->request_id, 'round_number' => 2]))
        ->toThrow(QueryException::class);
});

test('line assessments must reference the candidate exact revision', function (): void {
    $candidate = EvaluationCandidate::factory()->create();
    $otherLine = QuotationLine::factory()->create();

    expect(fn () => LineAssessment::factory()->create([
        'candidate_id' => $candidate->id, 'quotation_line_id' => $otherLine->id,
    ]))->toThrow(QueryException::class, 'line_assessment_matches_revision');
});

test('criterion assessments must reference the candidate request', function (): void {
    $candidate = EvaluationCandidate::factory()->create();
    $criterion = EvaluationCriterion::factory()->create();

    expect(fn () => CriterionAssessment::factory()->create([
        'candidate_id' => $candidate->id, 'criterion_id' => $criterion->id,
    ]))->toThrow(QueryException::class, 'criterion_assessment_matches_request');
});

test('frozen evaluation records cannot be edited', function (string $table, string $column, string $value): void {
    $round = EvaluationRound::factory()->frozen()->create();
    $id = $table === 'evaluation_rounds' ? $round->id : $round->recommended_candidate_id;

    expect(fn () => DB::table($table)->where('id', $id)->update([$column => $value]))->toThrow(QueryException::class);
})->with([
    'round' => ['evaluation_rounds', 'recommendation_reason', 'Changed'],
    'candidate' => ['evaluation_candidates', 'disposition', 'pending'],
]);

test('frozen assessments cannot be edited or created', function (): void {
    $round = EvaluationRound::factory()->frozen()->create();

    expect(fn () => LineAssessment::factory()->create(['candidate_id' => $round->recommended_candidate_id]))
        ->toThrow(QueryException::class, 'frozen_evaluation_children_are_immutable');
});

test('request creators cannot be assigned to approve their own evaluation', function (): void {
    $round = EvaluationRound::factory()->frozen()->create();

    expect(fn () => ApprovalCase::factory()->create([
        'round_id' => $round->id, 'assigned_approver_id' => $round->request->created_by,
    ]))->toThrow(QueryException::class, 'approval_requires_frozen_round');
});

test('a decided approval cannot be overwritten', function (): void {
    $case = ApprovalCase::factory()->approved()->create();

    expect(fn () => $case->update(['decision_reason' => 'Rewritten']))
        ->toThrow(QueryException::class, 'decided_approval_is_immutable');
});

test('awards retain the exact approved revision and reject duplicates', function (): void {
    $award = Award::factory()->create();

    expect($award->quotationRevision->total_amount)->toBe($award->approved_total);
    expect(fn () => Award::factory()->create([
        'request_id' => $award->request_id,
        'approval_case_id' => $award->approval_case_id,
        'quotation_revision_id' => $award->quotation_revision_id,
        'approved_total' => $award->approved_total,
        'currency_code' => $award->currency_code,
        'awarded_by' => $award->awarded_by,
    ]))->toThrow(QueryException::class);
});

test('award amounts must match approved evidence', function (): void {
    expect(fn () => Award::factory()->create(['approved_total' => '1.0000']))
        ->toThrow(QueryException::class, 'award_matches_approved_evidence');
});

test('one attachment cannot belong to multiple parent types', function (): void {
    $link = RequestAttachment::factory()->create();

    expect(fn () => RevisionAttachment::factory()->create(['attachment_id' => $link->attachment_id]))
        ->toThrow(QueryException::class, 'attachment_has_only_one_owner');
});

test('linked attachment contents cannot be replaced', function (): void {
    $link = RequestAttachment::factory()->create();

    expect(fn () => $link->attachment->update(['storage_key' => 'replaced.pdf']))
        ->toThrow(QueryException::class, 'linked_file_content_is_immutable');
});

test('a public clarification cannot retain a private supplier reference', function (): void {
    expect(fn () => ClarificationThread::factory()->create(['visibility' => 'all_invited']))
        ->toThrow(QueryException::class, 'thread_visibility_scope');
});

test('clarification messages are append only', function (): void {
    $record = ClarificationMessage::factory()->create();

    expect(fn () => $record->update(['body' => 'Rewritten']))->toThrow(QueryException::class, 'append_only_record');
});

test('audit events are append only', function (): void {
    $record = AuditEvent::factory()->create();

    expect(fn () => $record->update(['action' => 'Rewritten']))->toThrow(QueryException::class, 'append_only_record');
});

test('email retries cannot create duplicate in app notifications', function (): void {
    $notice = ProcurementNotification::factory()->create();
    $notice->update(['email_attempts' => 1, 'email_status' => 'failed']);

    expect(fn () => ProcurementNotification::factory()->create([
        'recipient_id' => $notice->recipient_id, 'event_key' => $notice->event_key,
    ]))->toThrow(QueryException::class);
});

test('referenced suppliers cannot be deleted', function (): void {
    $invitation = Invitation::factory()->create();

    expect(fn () => $invitation->supplier->delete())->toThrow(QueryException::class);
});

test('demo data covers three suppliers and a multi line request', function (): void {
    $this->seed(ProcurementDemoSeeder::class);

    $this->assertDatabaseCount('suppliers', 3);
    $this->assertDatabaseCount('request_items', 2);
    $this->assertDatabaseCount('evaluation_criteria', 4);
    $this->assertDatabaseCount('invitations', 3);
});
