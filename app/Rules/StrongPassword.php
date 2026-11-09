<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function __construct(protected int $min = 8) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        $missing = [];

        if (mb_strlen($value) < $this->min) {
            $missing[] = __('almeno :min caratteri', ['min' => $this->min]);
        }

        if (! preg_match('/[a-z]/', $value)) {
            $missing[] = __('una lettera minuscola');
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $missing[] = __('una lettera maiuscola');
        }

        if (! preg_match('/[0-9]/', $value)) {
            $missing[] = __('un numero');
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $missing[] = __('un simbolo');
        }

        if ($missing !== []) {
            $fail(__('La password deve contenere: :items.', ['items' => implode(', ', $missing)]));
        }
    }
}
