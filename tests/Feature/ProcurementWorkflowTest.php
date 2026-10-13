<?php

declare(strict_types=1);

use App\Actions\AssessCandidate;
use App\Actions\DeliverProcurementMail;
use App\Actions\FreezeEvaluation;
use App\Actions\PruneUploadChunks;
use App\Actions\PublishPurchaseRequest;
use App\Actions\SaveQuotation;
use App\Actions\StartEvaluation;
use App\Actions\SubmitQuotation;
use App\Concerns\ProcurementAccess;
use App\Concerns\QuotationMoney;
use App\Models\ApprovalCase;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Award;
use App\Models\Currency;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationRound;
use App\Models\Organization;
use App\Models\ProcurementNotification;
use App\Models\PurchaseRequest;
use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Models\RequestItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** @return array{buyer: User, approver: User, supplier: User, request: PurchaseRequest} */
function procurementScenario(): array
{
    $buyer = User::factory()->create()->refresh();
    $approver = User::factory()->create()->refresh();
    $buyer->assignRole('buyer');
    $approver->assignRole('approver');
    $currency = Currency::factory()->create(['code' => 'USD', 'minor_digits' => 2]);
    $request = PurchaseRequest::factory()->create(['created_by' => $buyer->id, 'assigned_approver_id' => $approver->id, 'currency_code' => $currency->code]);
    RequestItem::factory()->create(['request_id' => $request->id, 'quantity' => '10']);
    foreach (['price', 'delivery', 'payment', 'specification'] as $index => $category) {
        EvaluationCriterion::factory()->create(['request_id' => $request->id, 'name' => $category, 'category' => $category, 'display_order' => $index + 1]);
    }

    $company = Supplier::factory()->create();
    $supplier = User::factory()->supplierRepresentative($company)->create()->refresh();
    resolve(PublishPurchaseRequest::class)->handle($buyer, $request, $request->refresh()->lock_version, [$company->uuid]);

    return ['buyer' => $buyer, 'approver' => $approver, 'supplier' => $supplier, 'request' => $request->refresh()];
}

/** @return array{lock_version: int, valid_until: string, delivery_days: int, payment_terms: string, delivery_charge: string, lines: list<array{item_uuid: string, offered_description: string, unit_price: string, discount_amount: string, tax_amount: string, supplier_compliance_response: string}>} */
function quotationInput(PurchaseRequest $request): array
{
    return [
        'lock_version' => 0, 'valid_until' => now()->addMonth()->toDateString(), 'delivery_days' => 7,
        'payment_terms' => 'Payment within 30 days', 'delivery_charge' => '10000',
        'lines' => [['item_uuid' => $request->items()->firstOrFail()->uuid, 'offered_description' => 'Compliant product',
            'unit_price' => '25000', 'discount_amount' => '5000', 'tax_amount' => '44100', 'supplier_compliance_response' => 'Meets all specifications']],
    ];
}

/** @param array{buyer: User, approver: User, supplier: User, request: PurchaseRequest} $scenario */
function submittedQuote(array $scenario): QuotationRevision
{
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));

    return resolve(SubmitQuotation::class)->handle($scenario['supplier'], $scenario['request'], $revision->uuid, $revision->lock_version, (string) Str::uuid());
}

test('quotation arithmetic matches independent decimal fixtures', function (string $quantity, string $price, string $discount, string $tax, int $precision, string $net, string $total): void {
    $result = QuotationMoney::line($quantity, $price, $discount, $tax, $precision);

    expect(QuotationMoney::amount($result['line_net'])->compare(QuotationMoney::amount($net)))->toBe(0);
    expect(QuotationMoney::amount($result['line_total'])->compare(QuotationMoney::amount($total)))->toBe(0);
})->with([
    'documented example' => ['10', '25000', '5000', '44100', 2, '245000', '289100'],
    'half cent away from zero' => ['1', '0.005', '0', '0', 2, '0.01', '0.01'],
    'fractional quantity' => ['1.25', '2.50', '0.10', '0.20', 2, '3.03', '3.23'],
    'zero precision' => ['1', '2.5', '0', '0', 0, '3', '3'],
    'four digits' => ['0.0001', '0.5', '0', '0', 4, '0.0001', '0.0001'],
]);

test('supplier receives an exact durable receipt and retry creates no duplicate', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $this->actingAs($scenario['supplier'])->post(route('quotations.save', $scenario['request']), quotationInput($scenario['request']))->assertSessionHasNoErrors();
    $revision = QuotationRevision::query()->firstOrFail();
    $payload = ['revision_uuid' => $revision->uuid, 'lock_version' => $revision->lock_version, 'submission_key' => (string) Str::uuid()];

    $this->post(route('quotations.submit', $scenario['request']), $payload)->assertSessionHasNoErrors();
    $this->post(route('quotations.submit', $scenario['request']), $payload)->assertSessionHasNoErrors();

    expect($revision->refresh()->total_amount)->toBe('299100.0000');
    $this->assertDatabaseCount('quotation_revisions', 1);
    $this->assertDatabaseHas('procurement_notifications', ['recipient_id' => $scenario['supplier']->id, 'event_type' => 'quotation.receipt']);
});

test('declined suppliers cannot submit an existing draft until participation resumes', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
    $invitation = $scenario['request']->invitations()->firstOrFail();
    $payload = ['revision_uuid' => $revision->uuid, 'lock_version' => $revision->lock_version, 'submission_key' => (string) Str::uuid()];
    $this->actingAs($scenario['supplier'])->post(route('quotations.participation', $scenario['request']), [
        'operation' => 'decline', 'lock_version' => $invitation->lock_version, 'reason' => 'Unable to supply',
    ])->assertSessionHasNoErrors();

    $this->post(route('quotations.submit', $scenario['request']), $payload)
        ->assertSessionHasErrors(['workflow' => 'Reverse your decline before submitting a quotation.']);

    $this->assertDatabaseHas('quotation_revisions', [
        'id' => $revision->id, 'status' => 'draft', 'lock_version' => $revision->lock_version,
        'submission_key' => null, 'submitted_at' => null,
    ]);
    $this->assertDatabaseMissing('audit_events', ['action' => 'quotation.submitted']);
    $this->assertDatabaseMissing('procurement_notifications', ['event_type' => 'quotation.receipt']);

    $this->post(route('quotations.participation', $scenario['request']), [
        'operation' => 'participate', 'lock_version' => $invitation->refresh()->lock_version,
    ])->assertSessionHasNoErrors();
    $this->post(route('quotations.submit', $scenario['request']), $payload)->assertSessionHasNoErrors();

    $this->assertDatabaseHas('invitations', ['id' => $invitation->id, 'declined_at' => null]);
    $this->assertDatabaseHas('quotation_revisions', ['id' => $revision->id, 'status' => 'submitted', 'submission_key' => $payload['submission_key']]);
    $this->assertDatabaseHas('procurement_notifications', ['event_type' => 'quotation.receipt', 'recipient_id' => $scenario['supplier']->id]);
});

test('submission retries after withdrawal and deadline expiry do not revive the offer', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $revision = submittedQuote($scenario);
    $quotation = $revision->quotation;
    $this->actingAs($scenario['supplier'])->post(route('quotations.participation', $scenario['request']), [
        'operation' => 'withdraw', 'lock_version' => $quotation->lock_version, 'reason' => 'Cannot supply',
    ])->assertSessionHasNoErrors();
    $this->travelTo($scenario['request']->deadline_at);

    $this->post(route('quotations.submit', $scenario['request']), [
        'revision_uuid' => $revision->uuid, 'lock_version' => $revision->lock_version - 1,
        'submission_key' => $revision->submission_key,
    ])->assertSessionHasNoErrors();

    expect($quotation->refresh()->withdrawn_at)->not->toBeNull();
    $this->assertDatabaseCount('quotation_revisions', 1);
    expect(AuditEvent::query()->where('action', 'quotation.submitted')->count())->toBe(1);
    expect(ProcurementNotification::query()->where('event_type', 'quotation.receipt')->count())->toBe(1);
});

test('buyers see no commercial payload before close and never receive supplier drafts', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    submittedQuote($scenario);
    resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));

    $this->actingAs($scenario['buyer'])->get(route('procurement.show', $scenario['request']))->assertInertia(fn (Assert $page): Assert => $page->component('procurement/show')->has('revisions', 0));
    $this->travelTo($scenario['request']->deadline_at);
    $this->get(route('procurement.show', $scenario['request']))->assertInertia(fn (Assert $page): Assert => $page->has('revisions', 1)->where('revisions.0.status', 'submitted'));
});

test('uninvited suppliers cannot discover requests or their search counts', function (): void {
    $scenario = procurementScenario();
    $outsider = User::factory()->supplierRepresentative()->create()->refresh();

    $this->actingAs($outsider)->get(route('procurement.show', $scenario['request']))->assertNotFound();
    $this->get(route('procurement.index'))->assertInertia(fn (Assert $page): Assert => $page->where('requests.total', 0));
});

test('suppliers do not receive the internal approver identity', function (): void {
    $scenario = procurementScenario();

    $this->actingAs($scenario['supplier'])->get(route('procurement.show', $scenario['request']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('request.approver_uuid', null)->where('request.approver_name', null));
    $this->actingAs($scenario['buyer'])->get(route('procurement.show', $scenario['request']))
        ->assertInertia(fn (Assert $page): Assert => $page->whereNot('request.approver_name', null));
});

test('submission is refused exactly at the deadline without partial writes', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
    $this->travelTo($scenario['request']->deadline_at);

    $this->actingAs($scenario['supplier'])->post(route('quotations.submit', $scenario['request']), [
        'revision_uuid' => $revision->uuid, 'lock_version' => $revision->lock_version, 'submission_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('workflow');

    expect($revision->refresh()->status)->toBe('draft');
    $this->assertDatabaseMissing('procurement_notifications', ['event_type' => 'quotation.receipt']);
});

test('stale draft writes cannot overwrite a saved quotation', function (): void {
    $scenario = procurementScenario();
    $input = quotationInput($scenario['request']);
    resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], $input);

    $this->actingAs($scenario['supplier'])->post(route('quotations.save', $scenario['request']), $input)->assertSessionHasErrors('lock_version');

    $this->assertDatabaseCount('quotation_revisions', 1);
});

test('disabled sessions and unprivileged request mutations are rejected', function (): void {
    $user = User::factory()->create()->refresh();
    $this->actingAs($user)->post(route('procurement.store'), ['title' => 'Unauthorized', 'lock_version' => 0, 'items' => [], 'criteria' => []])->assertForbidden();
    $user->update(['status' => 'disabled']);

    $this->get(route('procurement.index'))->assertForbidden();
    $this->assertGuest();
});

test('independent approval creates one award and returned evidence remains immutable', function (string $decision): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $revision = submittedQuote($scenario);
    $this->travelTo($scenario['request']->deadline_at);
    $round = resolve(StartEvaluation::class)->handle($scenario['buyer'], $scenario['request'], $scenario['request']->lock_version);
    $candidate = $round->candidates()->firstOrFail();
    $data = ['lock_version' => $candidate->refresh()->lock_version, 'reason' => 'All conditions met.',
        'lines' => array_values($revision->lines()->get()->map(fn (QuotationLine $line): array => ['uuid' => $line->uuid, 'result' => 'pass', 'finding' => 'Specification verified.'])->values()->all()),
        'criteria' => array_values($scenario['request']->criteria()->get()->map(fn (EvaluationCriterion $criterion): array => ['uuid' => $criterion->uuid, 'result' => 'pass', 'finding' => 'Evidence verified.'])->values()->all())];
    resolve(AssessCandidate::class)->handle($scenario['buyer'], $scenario['request'], $candidate->uuid, $data);
    resolve(FreezeEvaluation::class)->handle($scenario['buyer'], $scenario['request'], [
        'lock_version' => $round->refresh()->lock_version, 'candidate_uuid' => $candidate->uuid, 'reason' => 'Meets the documented requirement.', 'participation_justification' => 'Only eligible offer; market checked.',
    ]);
    $case = ApprovalCase::query()->firstOrFail();
    $this->actingAs($scenario['buyer'])->post(route('evaluations.decide', $scenario['request']), ['lock_version' => $case->lock_version, 'operation' => 'approve'])->assertForbidden();

    $this->actingAs($scenario['approver'])->post(route('evaluations.decide', $scenario['request']), [
        'lock_version' => $case->lock_version, 'operation' => $decision, 'reason' => 'Reviewed evidence.',
    ])->assertSessionHasNoErrors();
    $this->post(route('evaluations.decide', $scenario['request']), [
        'lock_version' => $case->lock_version, 'operation' => 'approve',
    ])->assertSessionHasErrors('workflow');

    expect($round->refresh()->status)->toBe('frozen');
    expect(Award::query()->count())->toBe($decision === 'approve' ? 1 : 0);
    expect($scenario['request']->refresh()->status)->toBe(['approve' => 'awarded', 'return' => 'evaluating', 'reject' => 'rejected'][$decision]);
    expect(AuditEvent::query()->where('action', 'approval.'.$decision)->exists())->toBeTrue();
    $this->assertDatabaseHas('procurement_notifications', ['recipient_id' => $scenario['supplier']->id,
        'event_type' => $decision === 'approve' ? 'outcome.selected' : ($decision === 'reject' ? 'outcome.not_selected' : 'invitation.received')]);
    $this->get(route('procurement.show', $scenario['request']))->assertInertia(fn (Assert $page): Assert => $page
        ->has('rounds', $decision === 'return' ? 2 : 1)
        ->where('rounds.0.canDecide', $decision !== 'return'));
})->with(['approve', 'return', 'reject']);

test('buyers can save incomplete drafts but cannot publish them', function (): void {
    Organization::factory()->create();
    $buyer = User::factory()->create()->refresh();
    $buyer->assignRole('buyer');
    $this->actingAs($buyer)->post(route('procurement.store'), [
        'lock_version' => 0, 'title' => 'Incomplete draft', 'items' => [['description' => 'Desk', 'specification' => '', 'quantity' => '1', 'unit' => 'each']], 'criteria' => [],
    ])->assertSessionHasNoErrors();
    $request = PurchaseRequest::query()->firstOrFail();

    $this->post(route('procurement.publish', $request), ['lock_version' => $request->lock_version, 'supplier_uuids' => []])->assertSessionHasErrors('workflow');

    expect($request->refresh()->status)->toBe('draft');
    expect($request->items()->firstOrFail()->specification)->toBe('');
});

test('published requirements reject edits and stale extensions preserve the deadline', function (): void {
    $scenario = procurementScenario();
    $request = $scenario['request'];
    $deadline = $request->deadline_at;
    $this->actingAs($scenario['buyer'])->put(route('procurement.update', $request), [
        'lock_version' => $request->lock_version, 'title' => 'Changed scope', 'items' => [], 'criteria' => [],
    ])->assertSessionHasErrors('workflow');

    $this->post(route('procurement.change', $request), ['lock_version' => 0, 'operation' => 'extend', 'deadline_at' => now()->addMonths(2)->toISOString(), 'reason' => 'More time'])->assertSessionHasErrors('lock_version');

    expect($request->refresh()->deadline_at?->toISOString())->toBe($deadline?->toISOString());
    expect($request->title)->not->toBe('Changed scope');
});

test('validated documents are immediately available for submission and downloads remain private', function (): void {
    Storage::fake('local');
    $scenario = procurementScenario();
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
    config(['procurement.require_quotation_attachment' => true]);
    $this->actingAs($scenario['supplier'])->post(route('quotations.submit', $scenario['request']), [
        'revision_uuid' => $revision->uuid, 'lock_version' => $revision->lock_version, 'submission_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['workflow' => 'Attach a supporting document.']);
    $this->actingAs($scenario['supplier'])->post(route('files.store', $scenario['request']), [
        'parent_type' => 'revision', 'parent_uuid' => $revision->uuid,
        'file' => UploadedFile::fake()->createWithContent('offer.pdf', "%PDF-1.4\nTest quotation\n%%EOF"),
    ])->assertSessionHasNoErrors();
    $attachment = Attachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->storage_key);

    $this->post(route('quotations.submit', $scenario['request']), ['revision_uuid' => $revision->uuid, 'lock_version' => $revision->lock_version, 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $this->get(route('files.download', $attachment))->assertDownload('offer.pdf');
    $this->actingAs($scenario['buyer'])->get(route('files.download', $attachment))->assertNotFound();
    $outsider = User::factory()->supplierRepresentative()->create()->refresh();
    $this->actingAs($outsider)->get(route('files.download', $attachment))->assertNotFound();

    expect($revision->refresh()->status)->toBe('submitted');
});

/** @return array{content: string, upload_uuid: string} */
function uploadInChunks(TestCase $test, User $user, PurchaseRequest $request, string $content, string $name = 'offer.pdf'): array
{
    $uploadUuid = (string) Str::uuid();
    foreach (mb_str_split($content, 1024) as $index => $part) {
        $test->actingAs($user)->post(route('files.chunk', $request), [
            'upload_uuid' => $uploadUuid, 'chunk_index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent($name, $part),
        ], ['Accept' => 'application/json'])->assertNoContent();
    }

    return ['content' => $content, 'upload_uuid' => $uploadUuid];
}

function chunkedPdf(): string
{
    return "%PDF-1.4\n".str_repeat('Quotation evidence line. ', 100)."\n%%EOF";
}

test('a document uploaded in chunks is reassembled into one downloadable attachment', function (): void {
    Storage::fake('local');
    config(['procurement.upload_chunk_kilobytes' => 1]);
    $scenario = procurementScenario();
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
    $upload = uploadInChunks($this, $scenario['supplier'], $scenario['request'], chunkedPdf());

    $this->post(route('files.store', $scenario['request']), [
        'parent_type' => 'revision', 'parent_uuid' => $revision->uuid,
        'upload_uuid' => $upload['upload_uuid'], 'total_chunks' => 3, 'file_name' => 'offer.pdf',
    ])->assertSessionHasNoErrors();

    $attachment = Attachment::query()->firstOrFail();
    expect($attachment->original_name)->toBe('offer.pdf');
    $this->get(route('files.download', $attachment))->assertDownload('offer.pdf');
    expect($attachment->content_hash)->toBe(hash('sha256', $upload['content']));
    expect(Storage::disk('local')->get($attachment->storage_key))->toBe($upload['content']);
    expect(Storage::disk('local')->allFiles('procurement-chunks'))->toBe([]);
});

test('chunked uploads are rejected when incomplete, of a forbidden type, or owned by another user', function (string $case): void {
    Storage::fake('local');
    config(['procurement.upload_chunk_kilobytes' => 1]);
    $scenario = procurementScenario();
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
    $content = $case === 'forbidden type' ? str_repeat('<?php echo 1; ?>', 150) : chunkedPdf();
    $upload = uploadInChunks($this, $scenario['supplier'], $scenario['request'], $content, $case === 'forbidden type' ? 'offer.php' : 'offer.pdf');
    $uploader = $scenario['supplier'];
    if ($case === 'another user') {
        $uploader = User::factory()->create()->refresh();
        $uploader->update(['supplier_id' => $scenario['supplier']->supplier_id, 'supplier_membership_status' => 'active', 'supplier_assigned_by' => $scenario['buyer']->id, 'supplier_assigned_at' => now()]);
    }

    $this->actingAs($uploader)->post(route('files.store', $scenario['request']), [
        'parent_type' => 'revision', 'parent_uuid' => $revision->uuid, 'upload_uuid' => $upload['upload_uuid'],
        'total_chunks' => $case === 'incomplete' ? 4 : 3, 'file_name' => $case === 'forbidden type' ? 'offer.php' : 'offer.pdf',
    ])->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
})->with(['incomplete', 'forbidden type', 'another user']);

test('only people who can view a request may upload chunks to it', function (): void {
    Storage::fake('local');
    $scenario = procurementScenario();
    $outsider = User::factory()->supplierRepresentative()->create()->refresh();

    $this->actingAs($outsider)->post(route('files.chunk', $scenario['request']), [
        'upload_uuid' => (string) Str::uuid(), 'chunk_index' => 0,
        'chunk' => UploadedFile::fake()->createWithContent('offer.pdf', chunkedPdf()),
    ], ['Accept' => 'application/json'])->assertNotFound();

    expect(Storage::disk('local')->allFiles('procurement-chunks'))->toBe([]);
});

test('abandoned chunked uploads are pruned after a day', function (): void {
    Storage::fake('local');
    config(['procurement.upload_chunk_kilobytes' => 1]);
    $scenario = procurementScenario();
    $abandoned = uploadInChunks($this, $scenario['supplier'], $scenario['request'], chunkedPdf());
    $fresh = uploadInChunks($this, $scenario['supplier'], $scenario['request'], chunkedPdf());
    foreach (Storage::disk('local')->files('procurement-chunks/'.$scenario['supplier']->uuid.'/'.$abandoned['upload_uuid']) as $chunk) {
        touch(Storage::disk('local')->path($chunk), now()->subDays(2)->getTimestamp());
    }

    expect(resolve(PruneUploadChunks::class)->handle())->toBe(1);
    expect(Storage::disk('local')->directories('procurement-chunks/'.$scenario['supplier']->uuid))
        ->toBe(['procurement-chunks/'.$scenario['supplier']->uuid.'/'.$fresh['upload_uuid']]);
});

test('procurement processing and health checks work without a document scanner', function (): void {
    Cache::forget('procurement.worker_heartbeat');
    expect(Artisan::call('procurement:process', ['--health' => true]))->toBe(1);

    expect(Artisan::call('procurement:process'))->toBe(0);
    expect(Artisan::output())->toContain('Closed: 0; mail sent: 0.');

    expect(Artisan::call('procurement:process', ['--health' => true]))->toBe(0);
    expect(Artisan::output())->toContain('Procurement services are ready.');
    $this->travel(11)->minutes();
    expect(Artisan::call('procurement:process', ['--health' => true]))->toBe(1);
});

test('an invalid line rolls back all draft writes', function (): void {
    $scenario = procurementScenario();
    $input = quotationInput($scenario['request']);
    $input['lines'][0]['discount_amount'] = '999999';

    $this->actingAs($scenario['supplier'])->post(route('quotations.save', $scenario['request']), $input)->assertSessionHasErrors('workflow');

    $this->assertDatabaseCount('quotation_revisions', 0);
    $this->assertDatabaseCount('quotation_lines', 0);
});

test('a failed audit insert rolls back submission and receipt', function (): void {
    $scenario = procurementScenario();
    $revision = resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
    DB::unprepared("CREATE TRIGGER fail_submission_audit BEFORE INSERT ON audit_events WHEN NEW.action = 'quotation.submitted' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

    expect(fn () => resolve(SubmitQuotation::class)->handle($scenario['supplier'], $scenario['request'], $revision->uuid, $revision->lock_version, (string) Str::uuid()))
        ->toThrow(QueryException::class);

    expect($revision->refresh()->status)->toBe('draft');
    $this->assertDatabaseMissing('procurement_notifications', ['event_type' => 'quotation.receipt']);
});

test('administrators configure currencies organization suppliers and internal access', function (): void {
    Notification::fake();
    $admin = User::factory()->create()->refresh();
    $admin->assignRole('administrator');
    $this->actingAs($admin)->post(route('administration.store'), ['type' => 'currency', 'lock_version' => 0, 'code' => 'USD', 'name' => 'US dollar', 'minor_digits' => 2, 'active' => true])->assertSessionHasNoErrors();
    $this->post(route('administration.store'), ['type' => 'organization', 'lock_version' => 0, 'name' => 'Buying Organization', 'timezone' => 'Africa/Kigali', 'default_currency_code' => 'USD'])->assertSessionHasNoErrors();
    $this->post(route('administration.store'), ['type' => 'supplier', 'lock_version' => 0, 'legal_name' => 'Supplier One', 'contact_email' => 'supplier@example.test', 'status' => 'active'])->assertSessionHasNoErrors();
    $this->post(route('administration.store'), ['type' => 'user', 'lock_version' => 0, 'name' => 'New Buyer', 'email' => 'buyer@example.test', 'status' => 'active', 'roles' => ['buyer']])->assertSessionHasNoErrors();

    $created = User::query()->where('email', 'buyer@example.test')->firstOrFail();
    expect($created->getRoleNames()->all())->toBe(['buyer']);
    Notification::assertSentTo($created, ResetPassword::class);
    $this->get(route('administration.index'))->assertInertia(fn (Assert $page): Assert => $page->component('administration/index')->where('organization.name', 'Buying Organization'));
});

test('a failed mandatory finding excludes the quotation and blocks recommendation', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $revision = submittedQuote($scenario);
    $this->travelTo($scenario['request']->deadline_at);
    $round = resolve(StartEvaluation::class)->handle($scenario['buyer'], $scenario['request'], $scenario['request']->lock_version);
    $candidate = $round->candidates()->firstOrFail();
    $payload = [
        'candidate_uuid' => $candidate->uuid, 'lock_version' => $candidate->lock_version, 'reason' => 'Does not meet the required specification.',
        'lines' => $revision->lines()->get()->map(fn ($line): array => ['uuid' => $line->uuid, 'result' => 'fail', 'finding' => 'Wrong dimensions.'])->all(),
        'criteria' => $scenario['request']->criteria()->get()->map(fn ($criterion): array => ['uuid' => $criterion->uuid, 'result' => 'pass', 'finding' => 'Other terms meet conditions.'])->all(),
    ];
    $this->actingAs($scenario['buyer'])->post(route('evaluations.assess', $scenario['request']), $payload)->assertSessionHasNoErrors();

    $this->post(route('evaluations.freeze', $scenario['request']), ['candidate_uuid' => $candidate->uuid, 'lock_version' => $round->refresh()->lock_version, 'reason' => 'Cheapest offer', 'participation_justification' => 'Only offer'])->assertSessionHasErrors('candidate_uuid');

    expect($candidate->refresh()->disposition)->toBe('excluded');
    $this->assertDatabaseCount('approval_cases', 0);
});

test('withdrawal excludes the latest submission without reviving an earlier offer', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    submittedQuote($scenario);
    $latest = submittedQuote($scenario);
    $quotation = $latest->quotation()->firstOrFail();
    $this->actingAs($scenario['supplier'])->post(route('quotations.participation', $scenario['request']), ['operation' => 'withdraw', 'lock_version' => $quotation->lock_version, 'reason' => 'Cannot supply'])->assertSessionHasNoErrors();
    $this->travelTo($scenario['request']->deadline_at);

    $round = resolve(StartEvaluation::class)->handle($scenario['buyer'], $scenario['request'], $scenario['request']->lock_version);

    expect($round->candidates()->firstOrFail()->quotation_revision_id)->toBe($latest->id);
    expect($round->candidates()->firstOrFail()->disposition)->toBe('excluded');
    $this->assertDatabaseCount('quotation_revisions', 2);
});

/** @param array{buyer: User, approver: User, supplier: User, request: PurchaseRequest} $scenario */
function assessedRound(array $scenario, TestCase $test): EvaluationRound
{
    $revision = submittedQuote($scenario);
    $test->travelTo($scenario['request']->deadline_at);
    $round = resolve(StartEvaluation::class)->handle($scenario['buyer'], $scenario['request'], $scenario['request']->lock_version);
    $candidate = $round->candidates()->firstOrFail();
    $lines = [];
    foreach ($revision->lines()->get() as $line) {
        $lines[] = ['uuid' => $line->uuid, 'result' => 'pass', 'finding' => 'Specification verified.'];
    }

    $criteria = [];
    foreach ($scenario['request']->criteria()->get() as $criterion) {
        $criteria[] = ['uuid' => $criterion->uuid, 'result' => 'pass', 'finding' => 'Condition verified.'];
    }

    resolve(AssessCandidate::class)->handle($scenario['buyer'], $scenario['request'], $candidate->uuid, ['lock_version' => $candidate->lock_version, 'lines' => $lines, 'criteria' => $criteria]);

    return $round->refresh();
}

test('expired recommendations cannot become awards', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $round = assessedRound($scenario, $this);
    resolve(FreezeEvaluation::class)->handle($scenario['buyer'], $scenario['request'], ['lock_version' => $round->lock_version,
        'candidate_uuid' => $round->candidates()->firstOrFail()->uuid, 'reason' => 'Compliant offer.', 'participation_justification' => 'Market checked.']);
    $case = $round->approvalCase()->firstOrFail();
    $this->travel(2)->months();

    $this->actingAs($scenario['approver'])->post(route('evaluations.decide', $scenario['request']), ['lock_version' => $case->lock_version, 'operation' => 'approve'])->assertSessionHasErrors('workflow');

    expect($case->refresh()->status)->toBe('pending');
    $this->assertDatabaseCount('awards', 0);
});

test('material clarifications block freezing until the buyer resolves them', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $round = assessedRound($scenario, $this);
    $this->actingAs($scenario['supplier'])->post(route('clarifications.store', $scenario['request']), [
        'lock_version' => 0, 'subject' => 'Delivery clarification', 'body' => 'Please confirm access hours.',
    ])->assertSessionHasNoErrors();
    $thread = $scenario['request']->clarificationThreads()->firstOrFail();
    $payload = ['lock_version' => $round->lock_version, 'candidate_uuid' => $round->candidates()->firstOrFail()->uuid, 'reason' => 'Compliant offer.', 'participation_justification' => 'Market checked.'];

    $this->actingAs($scenario['buyer'])->post(route('evaluations.freeze', $scenario['request']), $payload)->assertSessionHasErrors('workflow');
    $this->post(route('clarifications.store', $scenario['request']), ['lock_version' => $thread->lock_version, 'thread_uuid' => $thread->uuid, 'operation' => 'resolve'])->assertSessionHasNoErrors();
    $this->post(route('evaluations.freeze', $scenario['request']), $payload)->assertSessionHasNoErrors();

    expect($round->refresh()->status)->toBe('frozen');
});

test('an approver with a buyer role cannot edit someone elses evaluation', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    $round = assessedRound($scenario, $this);
    $scenario['approver']->assignRole('buyer');
    $candidate = $round->candidates()->firstOrFail();

    $this->actingAs($scenario['approver'])->post(route('evaluations.assess', $scenario['request']), [
        'lock_version' => $candidate->lock_version, 'candidate_uuid' => $candidate->uuid, 'lines' => [], 'criteria' => [],
    ])->assertForbidden();

    expect($candidate->refresh()->disposition)->toBe('eligible');
});

test('email failure is retained for retry without duplicating the in-app receipt', function (): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    submittedQuote($scenario);
    $notificationCount = ProcurementNotification::query()->count();
    Mail::shouldReceive('raw')->andThrow(new RuntimeException('Test transport unavailable'));

    resolve(DeliverProcurementMail::class)->handle();
    resolve(DeliverProcurementMail::class)->handle();

    $this->assertDatabaseCount('procurement_notifications', $notificationCount);
    $this->assertDatabaseHas('procurement_notifications', ['event_type' => 'quotation.receipt', 'email_status' => 'failed', 'email_attempts' => 1, 'last_error_code' => 'transport_failure']);
    expect(QuotationRevision::query()->firstOrFail()->status)->toBe('submitted');
});

test('internal visibility checks have a bounded query cost for each role', function (?string $role, string $status, bool $internal, int $queries): void {
    $user = User::factory()->create(['status' => $status])->refresh();
    if ($role !== null) {
        $user->assignRole($role);
    }

    $this->expectsDatabaseQueryCount($queries);

    expect(ProcurementAccess::internal($user))->toBe($internal);
})->with([
    'buyer' => ['buyer', 'active', true, 2],
    'approver' => ['approver', 'active', true, 2],
    'auditor' => ['auditor', 'active', true, 2],
    'administrator only' => ['administrator', 'active', false, 1],
    'no role' => [null, 'active', false, 1],
    'disabled buyer' => ['buyer', 'disabled', false, 0],
]);

test('internal visibility rechecks role revocation on the same user instance', function (): void {
    $user = User::factory()->create()->refresh();
    $user->assignRole('buyer');

    expect(ProcurementAccess::internal($user))->toBeTrue();

    DB::table('model_has_roles')->where('model_id', $user->id)->delete();

    expect(ProcurementAccess::internal($user))->toBeFalse();
});

test('editor choices preserve creator exclusion and remain hidden from suppliers', function (): void {
    $scenario = procurementScenario();
    $scenario['buyer']->assignRole('approver');
    $otherBuyer = User::factory()->create()->refresh();
    $otherBuyer->assignRole('buyer');
    $otherBuyer->assignRole('approver');

    $this->actingAs($otherBuyer)->get(route('procurement.index'))->assertInertia(fn (Assert $page): Assert => $page
        ->where('currencies', fn (Collection $choices): bool => $choices->pluck('code')->contains('USD'))->has('suppliers', 1)
        ->where('approvers', fn (Collection $choices): bool => $choices->pluck('uuid')->contains($scenario['buyer']->uuid)
            && $choices->pluck('uuid')->doesntContain($otherBuyer->uuid)));
    $this->get(route('procurement.show', $scenario['request']))->assertInertia(fn (Assert $page): Assert => $page
        ->where('approvers', fn (Collection $choices): bool => $choices->pluck('uuid')->contains($otherBuyer->uuid)
            && $choices->pluck('uuid')->doesntContain($scenario['buyer']->uuid)));
    $this->actingAs($scenario['supplier'])->get(route('procurement.index'))->assertInertia(fn (Assert $page): Assert => $page
        ->has('currencies', 0)->has('approvers', 0)->has('suppliers', 0));
    $this->get(route('procurement.show', $scenario['request']))->assertInertia(fn (Assert $page): Assert => $page
        ->has('currencies', 0)->has('approvers', 0)->has('suppliers', 0));
});

test('invitations notify active members of each supplier once', function (): void {
    $scenario = procurementScenario();
    $companies = Supplier::factory()->count(3)->create();
    $activeMembers = [];
    $excludedMembers = [];
    foreach ($companies as $company) {
        $active = User::factory()->supplierRepresentative($company)->create();
        $activeMembers[] = $active->id;
        $inactive = User::factory()->supplierRepresentative($company)->create(['supplier_membership_status' => 'inactive']);
        $excludedMembers[] = $inactive->id;
        $disabled = User::factory()->supplierRepresentative($company)->create(['status' => 'disabled']);
        $excludedMembers[] = $disabled->id;
    }

    foreach ([1, 2] as $attempt) {
        resolve(PublishPurchaseRequest::class)->handle($scenario['buyer'], $scenario['request'],
            $scenario['request']->refresh()->lock_version, array_values($companies->map(fn (Supplier $company): string => $company->uuid)->all()));
    }

    foreach ($activeMembers as $recipientId) {
        $this->assertDatabaseHas('procurement_notifications', ['recipient_id' => $recipientId, 'event_type' => 'invitation.received']);
    }

    foreach ($excludedMembers as $recipientId) {
        $this->assertDatabaseMissing('procurement_notifications', ['recipient_id' => $recipientId]);
    }

    $this->assertDatabaseCount('invitations', 4);
    $this->assertDatabaseCount('procurement_notifications', 4);
});

test('list filters do not hide notifications for otherwise visible requests', function (): void {
    $scenario = procurementScenario();

    $this->actingAs($scenario['supplier'])->get(route('procurement.index', ['q' => 'No matching request title']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('requests.total', 0)
            ->has('notifications', 1)->where('notifications.0.request_uuid', $scenario['request']->uuid));
});

test('supplier overview exposes only invited deadlines and outstanding quotations', function (string $participation, int $tasks): void {
    $this->freezeTime();
    $scenario = procurementScenario();
    PurchaseRequest::factory()->create(['status' => 'open', 'deadline_at' => now()->addDay()]);
    $invitation = $scenario['request']->invitations()->firstOrFail();
    if ($participation === 'declined') {
        $invitation->update(['declined_at' => now()]);
    } elseif (in_array($participation, ['submitted', 'withdrawn', 'replacement_draft'], true)) {
        $revision = submittedQuote($scenario);
        if ($participation === 'withdrawn') {
            $revision->quotation()->update(['withdrawn_at' => now(), 'withdrawn_by' => $scenario['supplier']->id]);
        } elseif ($participation === 'replacement_draft') {
            resolve(SaveQuotation::class)->handle($scenario['supplier'], $scenario['request'], quotationInput($scenario['request']));
        }
    }

    $this->actingAs($scenario['supplier'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('approachingDeadlines.total', 1)->has('approachingDeadlines.data', 1)
            ->where('approachingDeadlines.data.0.uuid', $scenario['request']->uuid)
            ->missing('approachingDeadlines.data.0.assigned_approver_id')
            ->where('assignedTasks.total', $tasks)->has('assignedTasks.data', $tasks));
})->with(['invited' => ['invited', 1], 'declined' => ['declined', 0], 'submitted' => ['submitted', 0], 'withdrawn' => ['withdrawn', 1], 'replacement draft' => ['replacement_draft', 0]]);

test('supplier actions and approaching deadlines end exactly at closure', function (): void {
    $scenario = procurementScenario();
    $this->travelTo($scenario['request']->deadline_at);

    $this->actingAs($scenario['supplier'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('assignedTasks.total', 0)->has('assignedTasks.data', 0)
            ->where('approachingDeadlines.total', 0)->has('approachingDeadlines.data', 0));
});

test('approval work appears only for the assigned permitted approver and disappears after return', function (): void {
    $scenario = procurementScenario();
    $round = assessedRound($scenario, $this);
    resolve(FreezeEvaluation::class)->handle($scenario['buyer'], $scenario['request'], [
        'lock_version' => $round->lock_version, 'candidate_uuid' => $round->candidates()->firstOrFail()->uuid,
        'reason' => 'Meets requirements.', 'participation_justification' => 'Market checked.',
    ]);
    $other = User::factory()->create();
    $other->assignRole('approver');
    $this->actingAs($other)->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page->where('assignedTasks.total', 0));
    $scenario['buyer']->assignRole('approver');
    $this->actingAs($scenario['buyer'])->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page->where('assignedTasks.total', 0));

    $this->actingAs($scenario['approver'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('assignedTasks.total', 1)
            ->where('assignedTasks.data.0.uuid', $scenario['request']->uuid)
            ->where('assignedTasks.data.0.action', 'Review recommendation'));

    $case = $round->approvalCase()->firstOrFail();
    $this->post(route('evaluations.decide', $scenario['request']), ['lock_version' => $case->lock_version, 'operation' => 'return', 'reason' => 'Review the findings.'])->assertSessionHasNoErrors();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page->where('assignedTasks.total', 0));
});

test('buyers see invitation delivery failures without exposing recipient data to suppliers', function (): void {
    $scenario = procurementScenario();
    ProcurementNotification::query()->where('event_type', 'invitation.received')->update(['email_status' => 'failed', 'email_attempts' => 1]);
    submittedQuote($scenario);
    ProcurementNotification::query()->where('event_type', 'quotation.receipt')->update(['email_status' => 'failed']);

    $this->actingAs($scenario['buyer'])->get(route('procurement.show', $scenario['request']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('participation.0.invitation_email.failed', 1)
            ->missing('participation.0.invitation_email.recipient_id'));
    $this->actingAs($scenario['supplier'])->get(route('procurement.show', $scenario['request']))
        ->assertInertia(fn (Assert $page): Assert => $page->missing('participation.0.invitation_email'));
});
