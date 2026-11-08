<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoneRequest;
use App\Models\Farm;
use App\Models\Zone;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/**
 * Adds, renames and deletes the zones of a farm. Zones have no pages of their own: this all happens in dialogs on Farm detail.
 */
class ZoneController extends Controller
{
    use AuthorizesRequests;

    public function store(ZoneRequest $request, Farm $farm): RedirectResponse
    {
        $this->authorize('update', $farm);

        $zone = $farm->zones()->create([
            'user_id' => $farm->user_id,
            'name' => $request->validated('name') ?? $farm->nextZoneName(),
        ]);

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$zone->name} added.");
    }

    public function update(ZoneRequest $request, Farm $farm, Zone $zone): RedirectResponse
    {
        $this->authorize('update', $zone);

        $zone->update([
            'name' => $request->validated('name') ?? $farm->nextZoneName(),
        ]);

        return redirect()->route('farms.show', $farm)
            ->with('success', "Zone renamed to {$zone->name}.");
    }

    /**
     * Deletes the zone. Its sensors stay on the farm with no zone.
     */
    public function destroy(Farm $farm, Zone $zone): RedirectResponse
    {
        $this->authorize('delete', $zone);

        $zone->delete();

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$zone->name} deleted.");
    }
}
