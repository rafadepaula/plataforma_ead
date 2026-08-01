<?php

namespace App\Enums\Permissions;

/**
 * SPEC-00 §4 — the 3 fundamental Spatie roles for the platform.
 */
enum RolesEnum: string
{
    case ADMIN = 'admin';
    case GESTOR = 'gestor';
    case ALUNO = 'aluno';

    public static function label(string $role): string
    {
        return match ($role) {
            self::ADMIN->value => 'Administrador do Sistema',
            self::GESTOR->value => 'Gestor de Organização',
            self::ALUNO->value => 'Aluno Capacitando',
            default => $role,
        };
    }
}
