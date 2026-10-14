<?php

namespace App\Http\Requests;

use App\Enums\DeviceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
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
        return [
            'uuid' => 'required|string|unique:devices,uuid',
            'secret' => 'required|string',
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|in:'.implode(',', DeviceType::values()),
            'lora_aes_key' => 'nullable|string|size:32|required_if:type,lora',
        ];
    }
}
