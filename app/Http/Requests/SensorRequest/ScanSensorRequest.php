<?php

namespace App\Http\Requests\SensorRequest;

use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'uuid' => ['required', 'string'],
            'device_uuid' => ['required', 'string'],
            'manual_entry' => ['sometimes', 'boolean'],
            'confirm_update' => ['sometimes', 'boolean'],
        ]);
    }

    protected function sensorDevice(): ?Device
    {
        return Device::where('uuid', (string) $this->input('device_uuid'))->first();
    }

    protected function sensorType(): ?string
    {
        return $this->input('type');
    }
}
