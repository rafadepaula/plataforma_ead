<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 *  E2E coverage of the AJAX module reorder flow. Native HTML5
 * drag-and-drop is notoriously unreliable to emulate through WebDriver, so
 * this drives the same client-side code path `ModuleReorder.js` uses on a
 * real `drop` event (`window.ModuleReorder.persistOrder(list)`) after
 * rearranging the DOM nodes, then reloads the page and asserts the new
 * order survived a full round-trip through the AJAX endpoint.
 */
class ModuleReorderTest extends DuskTestCase
{
    public function test_reordering_modules_persists_after_reload(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $first = Module::factory()->for($course)->create(['title' => 'Módulo Um', 'order_index' => 0]);
        $second = Module::factory()->for($course)->create(['title' => 'Módulo Dois', 'order_index' => 1]);

        $this->browse(function (Browser $browser) use ($gestor, $course, $first, $second): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.modules.index', $course))
                ->waitFor('@module-list')
                ->assertSeeIn('@module-list', 'Módulo Um');

            // Move the second `<li>` above the first, then fire the same
            // persistence routine `ModuleReorder.js` runs on a real browser
            // `drop` event.
            $browser->script(
                "(function () {
                    var list = document.querySelector('[data-reorder-url]');
                    var second = document.querySelector('[data-id=\"{$second->id}\"]');
                    var first = document.querySelector('[data-id=\"{$first->id}\"]');
                    list.insertBefore(second, first);
                    window.ModuleReorder.persistOrder(list);
                })();"
            );

            $browser->waitForText('Ordem atualizada com sucesso.');

            $browser->refresh()
                ->waitFor('@module-list');
        });

        $this->assertSame(0, $second->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
    }
}
