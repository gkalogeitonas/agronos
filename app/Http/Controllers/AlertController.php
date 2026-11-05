<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    use AuthorizesRequests;

    /**
     * All open alerts of the signed-in tenant, newest first.
     */
    public function __invoke(): Response
    {
        $this->authorize('viewAny', Alert::class);

        $alerts = Alert::query()
            ->open()
            ->with(['subject' => fn (MorphTo $subject) => $subject->morphWith([Device::class => ['farm:id,name']])])
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts)->resolve(),
        ]);
    }
}
