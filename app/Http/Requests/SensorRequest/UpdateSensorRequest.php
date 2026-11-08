<?php

namespace App\Http\Requests\SensorRequest;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateSensorRequest extends SensorRequest
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
        return $this->commonRules();
    }

    protected function sensorDevice(): ?Device
    {
        return $this->routeSensor()->device;
    }

    protected function sensorType(): ?string
    {
        return $this->input('type', $this->routeSensor()->type);
    }

    private function routeSensor(): Sensor
    {
        /** @var Sensor */
        return $this->route('sensor');
    }
}
