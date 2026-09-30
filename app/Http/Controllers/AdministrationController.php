<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ManageProcurementAccess;
use App\Actions\ReadAdministration;
use App\Http\Requests\ManageProcurementAccessRequest;
use App\Http\Requests\ProcurementIndexRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class AdministrationController extends Controller
{
    public function index(ProcurementIndexRequest $request, ReadAdministration $read): Response
    {
        return Inertia::render('administration/index', $read->handle(($request->user() ?? abort(401))));
    }

    public function store(ManageProcurementAccessRequest $request, ManageProcurementAccess $manage): RedirectResponse
    {
        $emailDelivered = $manage->handle(($request->user() ?? abort(401)), $request->payload());

        return back()->with('success', $emailDelivered ? 'Configuration saved.' : 'Account saved. The password setup email could not be delivered. The user can request a password reset from the sign-in page.');
    }
}
