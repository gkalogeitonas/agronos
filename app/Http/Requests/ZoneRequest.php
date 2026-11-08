<?php

namespace App\Http\Requests;

use App\Models\Farm;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The controller authorises the farm and zone.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. An empty name gives the zone an automatic name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Farm $farm */
        $farm = $this->route('farm');
        /** @var Zone|null $zone */
        $zone = $this->route('zone');

        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('zones', 'name')->where('farm_id', $farm->id)->ignore($zone?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This farm already has a zone with this name.',
        ];
    }
}
