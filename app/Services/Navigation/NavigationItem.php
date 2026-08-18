<?php

namespace App\Services\Navigation;

use App\Models\User;

/**
 * Immutable value object describing a single sidebar/topbar entry,
 * declared once in {@see NavigationRegistry} and filtered/resolved by
 * {@see NavigationService} before reaching the Blade view.
 *
 * Visibility is controlled by three cooperating gates, ALL of which must
 * pass for the item to render (  — parity between the
 * menu and the route's own `role:`/Policy middleware):
 *
 *  1. `roles`          — non-empty allow-list of Spatie role names
 *                        (`admin`, `gestor`, `aluno`). An empty array
 *                        means "any authenticated user".
 *  2. `permissions`    — optional explicit `can()` checks (AND-ed).
 *  3. `routeResolver`  — optional closure returning the resolved URL
 *                        string, or `null` to hide the item entirely
 *                        (used by  contextual forum link, which
 *                        only renders when the Aluno has at least one
 *                        active enrollment).
 *  4. `sectionResolver` — optional closure returning the section heading
 *                        this item belongs to *for this user*, or `null`
 *                        to hide the item entirely. `section` is the
 *                        static fallback used when no resolver is set
 *                        ( the Admin's Organization-scoped items
 *                        move to "Impersonate" and vanish in global
 *                        context, while a Gestor keeps them in
 *                        "Administração").
 *
 * `activePatterns` are `routeIs()` wildcards — e.g. `['users.*']` keeps
 * the "Alunos & Usuários" parent highlighted on `users.create` /
 * `users.edit` sub-routes .
 */
final class NavigationItem
{
    /**
     * @param  list<string>  $activePatterns  `routeIs()` wildcards that keep this item highlighted.
     * @param  list<string>  $roles  Spatie role names allowed to see this item.
     * @param  list<string>  $permissions  Optional explicit `can()` permission checks.
     * @param  callable(User): (string|null)|null  $routeResolver  Contextual URL resolver; `null` result hides the item.
     * @param  callable(User): (int|string|null)|null  $badgeCallback  Pending-count badge; `null`/0 renders no badge.
     * @param  callable(User): (string|null)|null  $sectionResolver  Contextual section heading; `null` result hides the item.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $route,
        public readonly array $activePatterns,
        public readonly string $icon,
        public readonly array $roles,
        public readonly string $section,
        public array $permissions = [],
        public $routeResolver = null,
        public $badgeCallback = null,
        public $sectionResolver = null,
    ) {}
}
