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
 *  5. `childrenResolver` — optional closure returning this item's
 *                        always-visible sub-items *for this user*
 *                        ( the Aluno's enrolled-course blocks
 *                        under "Meus Cursos"), or an empty array when
 *                        the item has no children in the current
 *                        context. Unlike the gates above it never hides
 *                        the parent — the parent keeps its own URL.
 *  6. `childrenOnly`     — renders the item as a pure GROUP: the parent
 *                        anchor is never emitted and the children ARE
 *                        the whole entry ( the "Meus Cursos"
 *                        section, whose heading replaces the old parent
 *                        link). The declared `route`/`label`/`icon`
 *                        stay declared for the resolved shape, but the
 *                        Blade layer skips the anchor.
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
     * @param  callable(User): list<array{key: string, label: string, url: string, course_id: int|null, progress: int|null, is_course: bool, lessons_completed: int|null, lessons_total: int|null, forum_url: string|null, certificate_url: string|null}>|null  $childrenResolver  Per-user sub-items rendered below the parent (`course_id`/`progress` feed the child's active flag and progress bar; the `*_lessons`/`*_url` fields feed the rich course block).
     * @param  bool  $childrenOnly  Pure group item — the parent anchor never renders, the children are the whole entry.
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
        public $childrenResolver = null,
        public readonly bool $childrenOnly = false,
    ) {}
}
