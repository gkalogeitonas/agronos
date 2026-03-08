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
     *   "username": "gateway-mqtt-username",
     *   "payload": "{\"device_id\":\"node-uuid\",\"fcnt\":123,\"payload\":\"base64...\",\"rssi\":-85,\"snr\":7.5}"
     * }
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'payload' => ['required', 'string'],
        ];
    }
}
