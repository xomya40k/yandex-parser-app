<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YandexMapsOrganizationUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || empty($value)) {
            $fail('The :attribute must be a valid Yandex Maps organization card link.');

            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (!preg_match(
            '/^https?:\/\/(?:www\.)?(?:maps\.)?yandex\.(?:ru|com|by|kz|ua)\/(?:maps\/)?(?:org\/|-\/)/i',
            $value,
        )) {
            $fail('The :attribute must be a valid Yandex Maps organization card link.');
        }
    }
}
