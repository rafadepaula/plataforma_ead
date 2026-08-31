<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * validates a Brazilian CPF's two verification digits (mod 11
 * checksum algorithm). Purely computational: no DB lookups, no
 * dependency on tenancy/uniqueness (that is handled separately by
 * `Rule::unique('users', 'cpf')` on the fields that use this rule).
 */
class Cpf implements ValidationRule
{
    /**
     * Strips a CPF down to its digits so that the same document typed with
     * or without the `000.000.000-00` mask is one single value everywhere:
     * the `Rule::unique('users', 'cpf')` comparison, the `users.cpf` unique
     * index and the CPF-based dedup done by the CSV importer all compare
     * literal strings, so a masked variant would otherwise sneak a second
     * account past every one of them. Entry points that accept a CPF must
     * call this before validation/persistence.
     */
    public static function digits(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '' || ! $this->isValid($digits)) {
            $fail('O CPF informado é inválido.');
        }
    }

    protected function isValid(string $digits): bool
    {
        if (strlen($digits) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        $firstCheckDigit = $this->calculateCheckDigit(substr($digits, 0, 9), 10);

        if ($firstCheckDigit !== (int) $digits[9]) {
            return false;
        }

        $secondCheckDigit = $this->calculateCheckDigit(substr($digits, 0, 10), 11);

        return $secondCheckDigit === (int) $digits[10];
    }

    protected function calculateCheckDigit(string $base, int $startingWeight): int
    {
        $sum = 0;
        $weight = $startingWeight;

        foreach (str_split($base) as $digit) {
            $sum += (int) $digit * $weight;
            $weight--;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
