<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReadSuppliers;
use App\Http\Requests\AdministrationIndexRequest;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller
{
    public function index(AdministrationIndexRequest $request, ReadSuppliers $read): Response
    {
        return Inertia::render('administration/suppliers', $read->handle($request->payload()));
    }
}
