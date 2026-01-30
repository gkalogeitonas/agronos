<?php

namespace App\Http\Requests;

use App\Enums\SensorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSensorRequest extends FormRequest
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
            'device_uuid' => ['required', 'exists:devices,uuid'],
            'uuid' => ['required', 'string', 'unique:sensors,uuid'],
            'farm_id' => ['nullable', 'exists:farms,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'type' => ['nullable', Rule::in(SensorType::values())],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
