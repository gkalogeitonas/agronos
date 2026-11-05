<?php

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An alert line, matching the `AlertItem` frontend type. The wording comes from
 * the rule and the subject's current names, so renames show up without a write.
 * Expects a device subject with its `farm` loaded.
 *
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $device = $this->subject;

        return [
            'id' => $this->id,
            'title' => $this->rule->title(),
            'severity' => $this->severity,
            'device' => $device->name ?: $device->uuid,
            'farm' => $device->farm?->name ?? 'Unassigned',
            'href' => route('devices.show', $device),
            'opened_at' => $this->opened_at,
        ];
    }
}
