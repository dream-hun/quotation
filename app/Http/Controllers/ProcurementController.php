<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ChangePurchaseRequest;
use App\Actions\PublishPurchaseRequest;
use App\Actions\ReadProcurementIndex;
use App\Actions\ReadProcurementWorkspace;
use App\Actions\SavePurchaseRequest;
use App\Http\Requests\ProcurementIndexRequest;
use App\Http\Requests\ProcurementMutationRequest;
use App\Http\Requests\SavePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProcurementController extends Controller
{
    public function dashboard(ProcurementIndexRequest $request, ReadProcurementIndex $read): Response
    {
        return Inertia::render('procurement/index', $read->handle(($request->user() ?? abort(401)), $request->payload()));
    }

    public function index(ProcurementIndexRequest $request, ReadProcurementIndex $read): Response
    {
        return Inertia::render('procurement/index', $read->handle(($request->user() ?? abort(401)), $request->payload()));
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest, ReadProcurementWorkspace $read): Response
    {
        return Inertia::render('procurement/show', $read->handle(($request->user() ?? abort(401)), $purchaseRequest));
    }

    public function store(SavePurchaseRequestRequest $request, SavePurchaseRequest $save): RedirectResponse
    {
        $record = $save->handle(($request->user() ?? abort(401)), $request->payload());

        return to_route('procurement.show', $record)->with('success', 'Request draft saved.');
    }

    public function update(SavePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, SavePurchaseRequest $save): RedirectResponse
    {
        $save->handle(($request->user() ?? abort(401)), $request->payload(), $purchaseRequest);

        return back()->with('success', 'Request draft saved.');
    }

    public function publish(ProcurementMutationRequest $request, PurchaseRequest $purchaseRequest, PublishPurchaseRequest $publish): RedirectResponse
    {
        $publish->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->integer('lock_version'), $request->supplierUuids());

        return back()->with('success', 'Suppliers invited. Published requirements are frozen.');
    }

    public function change(ProcurementMutationRequest $request, PurchaseRequest $purchaseRequest, ChangePurchaseRequest $change): RedirectResponse
    {
        $record = $change->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->integer('lock_version'), $request->string('operation')->toString(), $request->string('reason')->toString(), $request->filled('deadline_at') ? $request->string('deadline_at')->toString() : null);

        return to_route('procurement.show', $record)->with('success', 'Request updated.');
    }
}
