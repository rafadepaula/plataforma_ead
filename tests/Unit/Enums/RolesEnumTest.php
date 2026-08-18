<?php

namespace Tests\Unit\Enums;

use App\Enums\Permissions\RolesEnum;
use PHPUnit\Framework\TestCase;

/**
 * RolesEnum backs the 3 fundamental Spatie roles and exposes
 * a human-readable label per role.
 */
class RolesEnumTest extends TestCase
{
    public function test_it_has_exactly_the_three_fundamental_roles(): void
    {
        $values = array_map(fn (RolesEnum $role) => $role->value, RolesEnum::cases());

        $this->assertSame(['admin', 'gestor', 'aluno'], $values);
    }

    public function test_admin_value(): void
    {
        $this->assertSame('admin', RolesEnum::ADMIN->value);
    }

    public function test_gestor_value(): void
    {
        $this->assertSame('gestor', RolesEnum::GESTOR->value);
    }

    public function test_aluno_value(): void
    {
        $this->assertSame('aluno', RolesEnum::ALUNO->value);
    }

    public function test_label_returns_expected_human_readable_labels(): void
    {
        $this->assertSame('Administrador do Sistema', RolesEnum::label(RolesEnum::ADMIN->value));
        $this->assertSame('Gestor de Organização', RolesEnum::label(RolesEnum::GESTOR->value));
        $this->assertSame('Aluno Capacitando', RolesEnum::label(RolesEnum::ALUNO->value));
    }

    public function test_label_falls_back_to_the_raw_role_for_unknown_values(): void
    {
        $this->assertSame('unknown-role', RolesEnum::label('unknown-role'));
    }
}
