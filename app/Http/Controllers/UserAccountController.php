<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReadUserAccounts;
use App\Http\Requests\AdministrationIndexRequest;
use Inertia\Inertia;
use Inertia\Response;

final class UserAccountController extends Controller
{
    public function index(AdministrationIndexRequest $request, ReadUserAccounts $read): Response
    {
        return Inertia::render('administration/users', $read->handle($request->payload()));
    }
}
