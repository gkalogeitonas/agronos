<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules shared by registering and editing a device.
 */
abstract class DeviceRequest extends FormRequest
{
    /**
     * The device's farm: one of the signed-in tenant's farms, or none while it is unassigned.
     *
     * @return array<int, ValidationRule|string>
     */
    protected function farmRules(): array
    {
        return ['nullable', Rule::exists('farms', 'id')->where('user_id', $this->user()->id)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'farm_id' => 'farm',
        ];
    }
}
