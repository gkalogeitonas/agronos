<?php

namespace App\Http\Requests;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adds or corrects a planting of a whole farm or, with a `zone_id`, of one of its zones. The farm comes from the route, never from the form,
 * and the zone must be one of that farm's.
 * A new planting that would follow the current open-ended one is refused with an `end_current` error that asks whether to end it;
 * sending `end_current` accepts, and the controller then ends that planting on the new start date.
 */
class PlantingRequest extends FormRequest
{
    private ?Planting $plantingToEnd = null;

    /**
     * Determine if the user is authorized to make this request. The controller authorises the farm and planting.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Farm $farm */
        $farm = $this->route('farm');

        return [
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('farm_id', $farm->id)],
            'crop_id' => ['required', 'integer', Crop::existsRule()],
            'variety' => ['nullable', 'string', 'max:255'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'end_current' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Farm $farm */
                $farm = $this->route('farm');
                $startedOn = $this->date('started_on');
                /** @var Planting|null $corrected */
                $corrected = $this->route('planting');
                $zoneId = $this->zoneId();
                $clashes = Planting::clashesWithin($farm, $zoneId, $startedOn, $this->date('ended_on'), $corrected);

                if ($clashes->isEmpty()) {
                    return;
                }

                $clash = $clashes->first();
                if ($clashes->count() === 1 && $corrected === null && $clash->canBeReplacedFrom($startedOn)) {
                    if ($this->boolean('end_current')) {
                        $this->plantingToEnd = $clash;
                    } else {
                        $validator->errors()->add('end_current', "{$clash->describePeriod()} is still growing. End it on {$startedOn->toDateString()} to add this crop?");
                    }

                    return;
                }

                $land = $zoneId === null ? 'A farm has one whole-farm planting' : 'A zone has one planting';
                $validator->errors()->add('started_on', "This overlaps the planting of {$clash->describePeriod()}. {$land} at a time.");
            },
        ];
    }

    /**
     * The zone the planting is of, or null for the whole farm. A form without a zone means the whole farm.
     */
    public function zoneId(): ?int
    {
        return $this->filled('zone_id') ? $this->integer('zone_id') : null;
    }

    /**
     * The planting's fields, with its land always set.
     *
     * @return array<string, mixed>
     */
    public function plantingAttributes(): array
    {
        return [...$this->safe()->except('end_current'), 'zone_id' => $this->zoneId()];
    }

    /**
     * The open-ended planting the grower agreed to end on the new planting's start date, once validated.
     */
    public function plantingToEnd(): ?Planting
    {
        return $this->plantingToEnd;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'crop_id.required' => 'Choose a crop.',
            'crop_id.exists' => 'Choose a crop from the list.',
            'zone_id.exists' => 'Choose a zone of this farm.',
            'ended_on.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
