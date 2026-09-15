<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveClarification;
use App\Http\Requests\SaveClarificationRequest;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;

final class ClarificationController extends Controller
{
    public function store(SaveClarificationRequest $request, PurchaseRequest $purchaseRequest, SaveClarification $save): RedirectResponse
    {
        $save->handle(($request->user() ?? abort(401)), $purchaseRequest, $request->payload());

        return back()->with('success', 'Clarification recorded.');
    }
}
