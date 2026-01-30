<?php

namespace App\Http\Requests\SensorRequest;

use App\Enums\SensorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanSensorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string'],
            'device_uuid' => ['required', 'exists:devices,uuid'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'type' => ['nullable', Rule::in(SensorType::values())],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
