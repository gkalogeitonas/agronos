<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCropRequest;
use App\Models\Crop;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Adds a tenant's own crop, from the crop select in the planting dialog. Editing and deleting them is not offered.
 */
class CropController extends Controller
{
    /**
     * Adds the crop and goes back to the page it was added from, handing the new crop over so the dialog can select it.
     */
    public function store(StoreCropRequest $request): RedirectResponse
    {
        $crop = Crop::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
        ]);

        Inertia::flash('createdCrop', ['id' => $crop->id, 'name' => $crop->name]);

        return back()->with('success', "{$crop->name} added to your crops.");
    }
}
