<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoRaWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validates the EMQX Rule Engine envelope.
     *
     * Expected format:
     * {
     *   "request": {
     *     "username": "lora_gateway",
     *     "payload": "{\"gateway_id\":\"lora_gateway\",\"rssi\":-46,\"snr\":13.75,\"raw_payload\":\"hex...\"}"
     *   }
     * }
     */
    public function rules(): array
    {
        return [
            'request' => ['required', 'array'],
            'request.username' => ['required', 'string'],
            'request.payload' => ['required', 'string'],
        ];
    }
}
