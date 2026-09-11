<?php

namespace App\Enums\Help;

use App\Enums\Permissions\RolesEnum;

/**
 * The minimum role required to see a help article on the public wiki.
 *
 * Visibility is hierarchical: an authenticated user sees every article
 * whose audience rank is at or below their own role rank (an Admin sees
 * everything, a Gestor sees `ALUNO` + `GESTOR`, ...). Guests and Alunos
 * therefore share the `ALUNO` (public) tier.
 */
enum HelpAudienceEnum: string
{
    case ALUNO = 'aluno';
    case PROFESSOR = 'professor';
    case GESTOR = 'gestor';
    case ADMIN = 'admin';

    /**
     * @return array<int, string>
     */
    public static function visibleValuesForRole(?string $role): array
    {
        $rank = match ($role) {
            RolesEnum::ADMIN->value => 4,
            RolesEnum::GESTOR->value => 3,
            RolesEnum::PROFESSOR->value => 2,
            RolesEnum::ALUNO->value => 1,
            default => 1,
        };

        return array_map(
            fn (self $audience): string => $audience->value,
            array_filter(
                self::cases(),
                fn (self $audience): bool => $audience->rank() <= $rank,
            ),
        );
    }

    private function rank(): int
    {
        return match ($this) {
            self::ALUNO => 1,
            self::PROFESSOR => 2,
            self::GESTOR => 3,
            self::ADMIN => 4,
        };
    }
}
