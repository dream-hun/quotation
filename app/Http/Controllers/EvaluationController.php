<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssessCandidate;
use App\Actions\DecideApproval;
use App\Actions\FreezeEvaluation;
use App\Actions\StartEvaluation;
use App\Http\Requests\AssessCandidateRequest;
use App\Http\Requests\FreezeEvaluationRequest;
use App\Http\Requests\ProcurementMutationRequest;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;

final class EvaluationController extends Controller
{
    public function start(ProcurementMutationRequest $request, PurchaseRequest $purchaseRequest, StartEvaluation $start): RedirectResponse
    {
        $start->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->integer('lock_version'));

        return back()->with('success', 'Evaluation opened.');
    }

    public function assess(AssessCandidateRequest $request, PurchaseRequest $purchaseRequest, AssessCandidate $assess): RedirectResponse
    {
        $assess->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->string('candidate_uuid')->toString(), $request->payload());

        return back()->with('success', 'Findings saved. Eligibility was checked.');
    }

    public function freeze(FreezeEvaluationRequest $request, PurchaseRequest $purchaseRequest, FreezeEvaluation $freeze): RedirectResponse
    {
        $freeze->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->payload());

        return back()->with('success', 'Evidence frozen and sent for independent approval.');
    }

    public function decide(ProcurementMutationRequest $request, PurchaseRequest $purchaseRequest, DecideApproval $decide): RedirectResponse
    {
        $decide->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->integer('lock_version'), $request->string('operation')->toString(), $request->string('reason')->toString());

        return back()->with('success', 'Approval decision recorded.');
    }
}
