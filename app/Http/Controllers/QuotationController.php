<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveQuotation;
use App\Actions\SubmitQuotation;
use App\Actions\WithdrawQuotation;
use App\Http\Requests\ProcurementMutationRequest;
use App\Http\Requests\SaveQuotationRequest;
use App\Http\Requests\SubmitQuotationRequest;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;

final class QuotationController extends Controller
{
    public function save(SaveQuotationRequest $request, PurchaseRequest $purchaseRequest, SaveQuotation $save): RedirectResponse
    {
        $save->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->payload());

        return back()->with('success', 'Draft saved. Submit explicitly when it is ready.');
    }

    public function submit(SubmitQuotationRequest $request, PurchaseRequest $purchaseRequest, SubmitQuotation $submit): RedirectResponse
    {
        $revision = $submit->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->string('revision_uuid')->toString(), $request->integer('lock_version'), $request->string('submission_key')->toString());

        return back()->with('success', 'Submitted revision '.$revision->revision_number.' at '.$revision->submitted_at?->toISOString().'. Receipt retained below.');
    }

    public function participation(ProcurementMutationRequest $request, PurchaseRequest $purchaseRequest, WithdrawQuotation $withdraw): RedirectResponse
    {
        $withdraw->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->string('operation')->toString(), $request->string('reason')->toString(), $request->integer('lock_version'));

        return back()->with('success', 'Participation updated.');
    }
}
