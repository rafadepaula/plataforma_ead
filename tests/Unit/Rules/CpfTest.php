<?php

namespace Tests\Unit\Rules;

use App\Rules\Cpf;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SPEC-18 — unit tests for the `App\Rules\Cpf` checksum-digit algorithm,
 * exercised in isolation via `Validator::make` (no DB, no HTTP layer).
 */
class CpfTest extends TestCase
{
    public function test_valid_cpf_digits_only_passes(): void
    {
        $validator = Validator::make(['cpf' => '11144477735'], ['cpf' => [new Cpf]]);

        $this->assertFalse($validator->fails());
    }

    public function test_valid_cpf_formatted_with_punctuation_passes(): void
    {
        $validator = Validator::make(['cpf' => '111.444.777-35'], ['cpf' => [new Cpf]]);

        $this->assertFalse($validator->fails());
    }

    public function test_second_known_valid_cpf_passes(): void
    {
        $validator = Validator::make(['cpf' => '529.982.247-25'], ['cpf' => [new Cpf]]);

        $this->assertFalse($validator->fails());
    }

    public function test_invalid_checksum_fails(): void
    {
        $validator = Validator::make(['cpf' => '111.444.777-36'], ['cpf' => [new Cpf]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('O CPF informado é inválido.', $validator->errors()->first('cpf'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function repeatedDigitSequencesProvider(): array
    {
        $sequences = [];

        for ($digit = 0; $digit <= 9; $digit++) {
            $sequence = str_repeat((string) $digit, 11);
            $sequences["all {$digit}s"] = [$sequence];
        }

        return $sequences;
    }

    #[DataProvider('repeatedDigitSequencesProvider')]
    public function test_all_repeated_digit_sequences_fail(string $sequence): void
    {
        $validator = Validator::make(['cpf' => $sequence], ['cpf' => [new Cpf]]);

        $this->assertTrue($validator->fails());
    }

    public function test_wrong_length_fails(): void
    {
        $validator = Validator::make(['cpf' => '123456789'], ['cpf' => [new Cpf]]);

        $this->assertTrue($validator->fails());
    }

    public function test_too_long_fails(): void
    {
        $validator = Validator::make(['cpf' => '111444777351234'], ['cpf' => [new Cpf]]);

        $this->assertTrue($validator->fails());
    }

    public function test_non_numeric_value_that_strips_to_empty_fails(): void
    {
        $validator = Validator::make(['cpf' => '---.---.---/--'], ['cpf' => [new Cpf]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('O CPF informado é inválido.', $validator->errors()->first('cpf'));
    }

    public function test_null_is_allowed_through_when_field_is_nullable_and_absent(): void
    {
        $validator = Validator::make([], ['cpf' => ['nullable', new Cpf]]);

        $this->assertFalse($validator->fails());
    }
}
