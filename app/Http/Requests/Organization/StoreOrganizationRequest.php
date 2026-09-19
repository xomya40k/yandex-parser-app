<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Rules\YandexMapsOrganizationUrlRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'yandex_maps_url' => ['required', 'string', 'max:512', new YandexMapsOrganizationUrlRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'yandex_maps_url.required' => 'A Yandex Maps organization URL is required.',
        ];
    }
}
