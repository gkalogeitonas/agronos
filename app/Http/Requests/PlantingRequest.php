<?php

namespace App\Http\Requests;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Adds or corrects a whole-farm planting. The farm comes from the route, never from the form.
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
        return [
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
                $clashes = Planting::clashesWithin($farm, $startedOn, $this->date('ended_on'), $corrected);

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

                $validator->errors()->add('started_on', "This overlaps the planting of {$clash->describePeriod()}. A farm has one whole-farm planting at a time.");
            },
        ];
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
            'ended_on.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
