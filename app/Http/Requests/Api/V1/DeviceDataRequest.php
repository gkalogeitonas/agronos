<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeviceDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Devices authenticated via Sanctum can post data
        return true;
    }

    /**
     * Expected payload format:
     * {
     *   "sensors": [
     *     { "uuid": "string", "value": numeric }
     *   ]
     * }
     */
    public function rules(): array
    {
        return [
            'sensors' => ['required', 'array', 'min:1'],
            'sensors.*.uuid' => ['required', 'string', 'max:64'],
            'sensors.*.value' => ['required', 'numeric'],
        ];
    }
}
