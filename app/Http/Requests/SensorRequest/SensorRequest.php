<?php

namespace App\Http\Requests\SensorRequest;

use App\Enums\SensorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SensorRequest extends FormRequest
{
    public const UNREGISTERED_DEVICE_MESSAGE = 'No device with this UUID was found in your account. Check the UUID, or register the device first.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Common validation rules shared across sensor requests.
     */
    protected function commonRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'farm_id' => ['nullable', 'exists:farms,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'type' => ['nullable', Rule::in(SensorType::values())],
        ];
    }

    /**
     * Common validation messages and attribute names.
     */
    public function messages(): array
    {
        return [
            'uuid.unique' => 'A sensor with this UUID already exists.',
        ];
    }

    public function attributes(): array
    {
        return [
            'device_uuid' => 'device UUID',
            'farm_id' => 'farm',
            'lat' => 'latitude',
            'lon' => 'longitude',
        ];
    }
}
