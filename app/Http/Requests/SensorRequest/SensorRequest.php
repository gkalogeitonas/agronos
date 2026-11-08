<?php

namespace App\Http\Requests\SensorRequest;

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Zone;
use Closure;
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
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'type' => ['nullable', Rule::in(SensorType::values())],
            'zone_id' => ['nullable', 'integer', $this->zoneOnDeviceFarm(...)],
        ];
    }

    /**
     * The device the sensor is or will be on, if the user has it.
     */
    protected function sensorDevice(): ?Device
    {
        return Device::where('uuid', (string) $this->input('device_uuid'))->first();
    }

    /**
     * The sensor's type once the request is applied.
     */
    protected function sensorType(): ?string
    {
        return $this->input('type');
    }

    /**
     * A sensor's zone must be one of the user's zones on its device's farm, and diagnostic
     * sensors are in no zone (ADR-0009). The tenant scope hides other users' zones.
     *
     * @param  Closure(string): void  $fail
     */
    protected function zoneOnDeviceFarm(string $attribute, mixed $value, Closure $fail): void
    {
        $type = SensorType::tryFrom((string) $this->sensorType());
        if ($type !== null && ! $type->isFarmRelevant()) {
            $fail('Diagnostic sensors cannot be in a zone.');

            return;
        }

        $device = $this->sensorDevice();
        if ($device !== null && $device->farm_id === null) {
            $fail('Put the device on a farm before choosing a zone.');

            return;
        }

        $zone = Zone::find($value);
        if ($zone === null || ($device !== null && (int) $zone->farm_id !== (int) $device->farm_id)) {
            $fail('Choose a zone of the farm the sensor\'s device is on.');
        }
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
            'lat' => 'latitude',
            'lon' => 'longitude',
        ];
    }
}
