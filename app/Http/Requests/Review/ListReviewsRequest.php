<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class ListReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 50),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
