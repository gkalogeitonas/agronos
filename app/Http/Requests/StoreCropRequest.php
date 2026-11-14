<?php

namespace App\Http\Requests;

use App\Models\Crop;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Adds a crop for the signed-in tenant's own use. Only a name is asked for, and it must not repeat,
 * ignoring case, any crop the tenant can already choose: the catalogue and their own crops (ADR-0010).
 */
class StoreCropRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Any signed-in user may add a crop for themselves.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Runs of spaces inside the name are collapsed, so "Sweet  potato" matches "Sweet potato".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::squish($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, string $value, Closure $fail): void {
                    if (Crop::nameIsTaken($value)) {
                        $fail('This crop is already in the list.');
                    }
                },
            ],
        ];
    }
}
