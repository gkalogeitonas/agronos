<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndPlantingRequest;
use App\Http\Requests\PlantingRequest;
use App\Models\Farm;
use App\Models\Planting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/**
 * Adds, corrects, ends and deletes the whole-farm plantings of a farm. They have no pages of their own: this all happens in dialogs on Farm detail.
 */
class PlantingController extends Controller
{
    use AuthorizesRequests;

    public function store(PlantingRequest $request, Farm $farm): RedirectResponse
    {
        $this->authorize('update', $farm);

        $planting = $farm->plantings()->create([
            ...$request->validated(),
            'user_id' => $farm->user_id,
        ]);

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} added.");
    }

    public function update(PlantingRequest $request, Farm $farm, Planting $planting): RedirectResponse
    {
        $this->ensureWholeFarm($planting);
        $this->authorize('update', $planting);

        $planting->update($request->validated());

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} updated.");
    }

    /**
     * Ends the planting on the given day, today by default.
     */
    public function end(EndPlantingRequest $request, Farm $farm, Planting $planting): RedirectResponse
    {
        $this->ensureWholeFarm($planting);
        $this->authorize('update', $planting);

        $planting->update(['ended_on' => $request->date('ended_on')]);

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} ended.");
    }

    public function destroy(Farm $farm, Planting $planting): RedirectResponse
    {
        $this->ensureWholeFarm($planting);
        $this->authorize('delete', $planting);

        $planting->delete();

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} deleted.");
    }

    /**
     * These routes manage whole-farm plantings only; a zone's planting is not theirs to change.
     */
    private function ensureWholeFarm(Planting $planting): void
    {
        abort_unless($planting->zone_id === null, 404);
    }
}
