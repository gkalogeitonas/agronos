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
            // Accept either a wrapped payload (EMQX envelope) or the raw payload directly.
            'request' => ['sometimes', 'array'],

            // When using the wrapper
            'request.username' => ['required_with:request', 'string'],
            'request.payload' => ['required_with:request', 'string'],

            // When sending the payload directly (common in some EMQX setups)
            'username' => ['required_without:request', 'string'],
            'payload' => ['required_without:request', 'string'],
        ];
    }
}
