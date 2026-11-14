<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndPlantingRequest;
use App\Http\Requests\PlantingRequest;
use App\Models\Farm;
use App\Models\Planting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Adds, corrects, ends and deletes the plantings of a farm and its zones. They have no pages of their own: this all happens in dialogs on Farm detail.
 */
class PlantingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Adds the planting. When the grower agreed to replace the current planting, that one ends on the new start date in the same transaction.
     */
    public function store(PlantingRequest $request, Farm $farm): RedirectResponse
    {
        $this->authorize('update', $farm);

        $ended = $request->plantingToEnd();

        $planting = DB::transaction(function () use ($request, $farm, $ended): Planting {
            $ended?->update(['ended_on' => $request->date('started_on')]);

            return $farm->plantings()->create([
                ...$request->plantingAttributes(),
                'user_id' => $farm->user_id,
            ]);
        });

        $message = $ended ? "{$ended->crop->name} ended and {$planting->crop->name} added." : "{$planting->crop->name} added.";

        return redirect()->route('farms.show', $farm)->with('success', $message);
    }

    public function update(PlantingRequest $request, Farm $farm, Planting $planting): RedirectResponse
    {
        $this->authorize('update', $planting);

        $planting->update($request->plantingAttributes());

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} updated.");
    }

    /**
     * Ends the planting on the given day, today by default.
     */
    public function end(EndPlantingRequest $request, Farm $farm, Planting $planting): RedirectResponse
    {
        $this->authorize('update', $planting);

        $planting->update(['ended_on' => $request->date('ended_on')]);

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} ended.");
    }

    public function destroy(Farm $farm, Planting $planting): RedirectResponse
    {
        $this->authorize('delete', $planting);

        $planting->delete();

        return redirect()->route('farms.show', $farm)
            ->with('success', "{$planting->crop->name} deleted.");
    }
}
