<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A Swedish organisationsnummer: ten digits, the last a Luhn check digit, as NNNNNN-NNNN or without the dash. */
class OrgNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if (strlen($digits) !== 10 || ! self::luhn($digits)) {
            $fail(__('receipt.org_number_invalid'));
        }
    }

    public static function format(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        return substr($digits, 0, 6).'-'.substr($digits, 6);
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        foreach (str_split($digits) as $i => $digit) {
            $n = (int) $digit * ($i % 2 === 0 ? 2 : 1);
            $sum += intdiv($n, 10) + $n % 10;
        }

        return $sum % 10 === 0;
    }
}
