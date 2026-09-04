<?php

namespace App\Enums\Permissions;

/**
 * the 4 fundamental Spatie roles for the platform.
 */
enum RolesEnum: string
{
    case ADMIN = 'admin';
    case GESTOR = 'gestor';
    case ALUNO = 'aluno';
    case PROFESSOR = 'professor';

    public static function label(string $role): string
    {
        return match ($role) {
            self::ADMIN->value => 'Administrador do Sistema',
            self::GESTOR->value => 'Gestor de Organização',
            self::ALUNO->value => 'Aluno Capacitando',
            self::PROFESSOR->value => 'Professor',
            default => $role,
        };
    }
}
