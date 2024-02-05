<?php
declare(strict_types=1);

namespace Litalico\EgR2\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use function is_int;

/**
 * Validation rule class of type integer
 * @package Litalico\EgR2\Rules
 */
class Integer implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_int($value) === false) {
            $fail('The :attribute must be integer.');
        }
    }
}
