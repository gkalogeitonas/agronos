<?php

namespace App\Http\Requests\SensorRequest;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreSensorRequest extends SensorRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'device_uuid' => ['required', 'string'],
            'uuid' => ['required', 'string', 'unique:sensors,uuid'],
        ]);
    }
}
