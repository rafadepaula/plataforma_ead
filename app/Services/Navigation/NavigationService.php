<?php

namespace App\Services\Navigation;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Reads the declarative {@see NavigationRegistry} and produces the
 * fully filtered, URL-resolved, badge-enriched list of
 * {@see NavigationSection}s for the acting user (SPEC-17 §2 — the
 * service layer that replaces the previously imperative Blade menu).
 *
 * Filtering pipeline per item (all gates must pass — RN38/RN40 parity
 * with the route's own `role:`/Policy middleware):
 *
 *   1. `roles` allow-list intersects the user's roles (empty = any auth user).
 *   2. `permissions` array must all pass `$user->can(...)`.
 *   3. `routeResolver` (if set) must return a non-null URL — a `null`
 *      result means the item is contextually unavailable (RF39 forum).
 *      Items without a resolver fall back to `route($item->route)`, but
 *      only if that route name is actually registered (RF36 — never a
 *      dead `#` link).
 *   4. `sectionResolver` (if set) must return a non-null section
 *      heading — a `null` result means the item has no meaningful place
 *      in this user's menu and is dropped (UX-001 — the Admin's
 *      Organization-scoped items in global context).
 *
 * Empty sections (no visible items) are dropped so the sidebar never
 * renders an orphan heading.
 */
final class NavigationService
{
    public function __construct(
        private readonly NavigationRegistry $registry,
        private readonly Request $request,
    ) {}

    /**
     * @return list<NavigationSection>
     */
    public function build(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $sections = [];
        $order = array_flip($this->registry->sectionOrder());

        foreach ($this->registry->items() as $item) {
            $resolved = $this->resolve($item, $user);

            if ($resolved === null) {
                continue;
            }

            // UX-001 — the heading comes from the resolved item, not the
            // registry's static `section`: it may have been rewritten
            // per-user by a `sectionResolver`.
            $sectionTitle = $resolved['section'];

            if (! isset($sections[$sectionTitle])) {
                $sections[$sectionTitle] = new NavigationSection($sectionTitle);
            }

            $sections[$sectionTitle]->items[] = $resolved;
        }

        // Drop empty sections and preserve the registry's declared
        // display order — an Aluno never sees the "Administração"
        // heading just because it exists in `sectionOrder()` (RN38).
        $result = array_filter($sections, fn ($section) => $section->items !== []);

        uasort($result, function (NavigationSection $a, NavigationSection $b) use ($order): int {
            return ($order[$a->title] ?? PHP_INT_MAX) <=> ($order[$b->title] ?? PHP_INT_MAX);
        });

        return array_values($result);
    }

    /**
     * Resolve a single item for the acting user, returning a new
     * item-shaped array with the concrete `url`, `active` flag and
     * `badge` value, or `null` if the item must be hidden.
     *
     * @return array{key: string, label: string, url: string, active: bool, badge: int|string|null, icon: string, section: string}|null
     */
    private function resolve(NavigationItem $item, User $user): ?array
    {
        if (! $this->passesRoleGate($item, $user)) {
            return null;
        }

        if (! $this->passesPermissionGate($item, $user)) {
            return null;
        }

        $section = $this->resolveSection($item, $user);

        if ($section === null) {
            return null;
        }

        $url = $this->resolveUrl($item, $user);

        if ($url === null) {
            return null;
        }

        return [
            'key' => $item->key,
            'label' => $item->label,
            'url' => $url,
            'active' => $this->isActive($item),
            'badge' => $this->resolveBadge($item, $user),
            'icon' => $item->icon,
            'section' => $section,
        ];
    }

    /**
     * UX-001 — an item's heading may depend on the acting user's context
     * (Admin in global scope vs. impersonating an Organization). Items
     * without a `sectionResolver` keep their statically declared
     * `section`; a resolver returning `null` hides the item entirely.
     */
    private function resolveSection(NavigationItem $item, User $user): ?string
    {
        if ($item->sectionResolver === null) {
            return $item->section;
        }

        return ($item->sectionResolver)($user);
    }

    private function passesRoleGate(NavigationItem $item, User $user): bool
    {
        if ($item->roles === []) {
            return true;
        }

        foreach ($item->roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    private function passesPermissionGate(NavigationItem $item, User $user): bool
    {
        foreach ($item->permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    private function resolveUrl(NavigationItem $item, User $user): ?string
    {
        if ($item->routeResolver !== null) {
            return ($item->routeResolver)($user);
        }

        // RF36 — never emit a dead `#` link. If the configured route
        // name isn't registered, the item is hidden rather than
        // rendered as an unreachable anchor.
        if (! Route::has($item->route)) {
            return null;
        }

        return route($item->route);
    }

    private function isActive(NavigationItem $item): bool
    {
        foreach ($item->activePatterns as $pattern) {
            if ($this->request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function resolveBadge(NavigationItem $item, User $user): int|string|null
    {
        if ($item->badgeCallback === null) {
            return null;
        }

        $count = ($item->badgeCallback)($user);

        // A zero count renders no badge at all (keeps the sidebar quiet
        // when there is nothing pending).
        return $count === 0 ? null : $count;
    }
}
