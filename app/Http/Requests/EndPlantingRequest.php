<?php

namespace App\Http\Requests;

use App\Models\Planting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ends a planting on the given day, today when none is given.
 */
class EndPlantingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The controller authorises the planting.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An empty end date means today, so it is validated like any other.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('ended_on')) {
            $this->merge(['ended_on' => now()->toDateString()]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Planting $planting */
        $planting = $this->route('planting');

        $rules = ['required', 'date'];
        if ($planting->started_on) {
            $rules[] = 'after_or_equal:'.$planting->started_on->toDateString();
        }

        return ['ended_on' => $rules];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ended_on.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
