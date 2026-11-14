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
 */
class PlantingRequest extends FormRequest
{
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
                $clash = Planting::clashWithin(
                    $farm,
                    $this->date('started_on'),
                    $this->date('ended_on'),
                    $this->route('planting'),
                );

                if ($clash) {
                    $validator->errors()->add('started_on', "This overlaps the planting of {$clash->describePeriod()}. A farm has one whole-farm planting at a time.");
                }
            },
        ];
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
