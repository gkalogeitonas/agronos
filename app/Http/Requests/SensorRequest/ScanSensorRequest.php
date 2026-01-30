<?php

namespace App\Http\Requests\SensorRequest;

use Illuminate\Validation\Rule;
use App\Enums\SensorType;

class ScanSensorRequest extends SensorRequest
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
        return array_merge($this->commonRules(), [
            'uuid' => ['required', 'string'],
            'device_uuid' => ['required', 'exists:devices,uuid'],
        ]);
    }
}
