<?php

namespace App\Services\Navigation;

/**
 * A renderable navigation group ("Administração", "Aprendizado",
 * "Sistema") holding its already-filtered {@see NavigationItem}s. Built
 * by {@see NavigationService::build()} after per-item access checks;
 * sections with zero visible items are dropped entirely so the Blade
 * `@foreach` never emits an empty heading (SPEC-17 RN38).
 */
final class NavigationSection
{
    public string $title;

    /** @var list<NavigationItem> */
    public array $items;

    /**
     * @param  string  $title  Section heading displayed in the sidebar.
     * @param  list<NavigationItem>  $items  Already-filtered items.
     */
    public function __construct(string $title, array $items = [])
    {
        $this->title = $title;
        $this->items = $items;
    }
}
