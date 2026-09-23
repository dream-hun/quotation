<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Concerns\ProcurementAccess;
use App\Enums\ProcurementPermission;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => ['success' => $request->session()->get('success')],
            'isAdministrator' => $request->user() !== null && ProcurementAccess::can($request->user(), ProcurementPermission::ManageAccess),
            'auth' => [
                'user' => $request->user(),
            ],
            'uploads' => [
                'maxKilobytes' => config()->integer('procurement.max_upload_kilobytes'),
                'chunkKilobytes' => config()->integer('procurement.upload_chunk_kilobytes'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
