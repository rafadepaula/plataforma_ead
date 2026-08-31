<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for Forum topic creation (desktop modal and mobile FAB),
 * listing, empty state, pin/unpin interactions, and the asynchronous
 * `ForumPolling.js` reply loop — its card parity with the Blade partial,
 * the report chain a polled card has to go through to reach the moderation
 * queue, its behaviour under the `forum-replies.fetch` rate limit, and the
 * terminal teardown for every status after which the endpoint can never
 * answer the page again (404 moderation removal, 401/419 expired session,
 * 403 revoked access) as opposed to a transient one.
 *
 * Frontend build gotcha: every change to `resources/js/modules/ForumPolling.js`
 * must be followed by `vendor/bin/sail npm run build`, otherwise `public/build`
 * serves the previous bundle and the polling cases fail for the wrong reason.
 */
class ForumPollingAndInteractionDuskTest extends DuskTestCase
{
    /**
     * Below the `lg` breakpoint the header "Novo tópico" button is hidden and
     * the FAB takes over; 1440px keeps the desktop layout.
     */
    private const MOBILE_VIEWPORT = [390, 900];

    private const DESKTOP_VIEWPORT = [1440, 900];

    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    private function gestorFor(Organization $org): User
    {
        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        return $gestor;
    }

    public function test_forum_topic_creation_listing_and_pin_interactions_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $gestor = $this->gestorFor($org);

        $this->browse(function (Browser $browser) use ($student, $gestor, $course): void {
            // 1. The empty state greets the student before any topic exists.
            $browser->resize(...self::DESKTOP_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.index', $course))
                ->waitFor('@no-topics')
                ->assertSeeInIgnoringCase('@no-topics', 'Nenhum tópico por aqui')
                ->assertSeeInIgnoringCase('@no-topics', 'Abra o primeiro tópico e comece a conversa com a turma.');

            // 2. Student creates a topic through the desktop header modal.
            //
            //    The redirect target cannot be computed before the topic row
            //    exists, so the wait is on rendered content first; only then is
            //    the row looked up and the canonical URL asserted.
            $browser->waitFor('@new-topic-button')
                ->click('@new-topic-button')
                ->waitForModalShown('new-topic-modal')
                ->type('@new-topic-title', 'Tópico de Dúvidas de Boas-Vindas')
                ->type('@new-topic-content', 'Olá turma, este é o tópico oficial de discussão do curso.')
                ->click('@new-topic-submit')
                ->waitForText('Tópico de Dúvidas de Boas-Vindas')
                ->waitFor('@topic-content')
                ->assertSee('Olá turma, este é o tópico oficial de discussão do curso.');

            $topic = ForumTopic::query()
                ->where('course_id', $course->id)
                ->where('title', 'Tópico de Dúvidas de Boas-Vindas')
                ->firstOrFail();

            $browser->assertPathIs(parse_url(route('forum.show', [$course, $topic]), PHP_URL_PATH));

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'title' => 'Tópico de Dúvidas de Boas-Vindas',
                'user_id' => $student->id,
                'is_pinned' => false,
            ]);

            // 3. The topic now shows as a card in the listing and opens.
            $browser->visit(route('forum.index', $course))
                ->assertMissing('@no-topics')
                ->waitFor('@topic-row-'.$topic->id)
                ->assertMissing('@pinned-badge-'.$topic->id)
                ->click('@open-topic-'.$topic->id)
                ->waitForLocation(route('forum.show', [$course, $topic]))
                ->waitFor('@topic-content')
                ->assertSee('Olá turma, este é o tópico oficial de discussão do curso.');

            // 4. Gestor pins the topic from the index. The pin form is a DOM
            //    sibling of the (stretched-link) topic anchor, so the click
            //    must land on the button and not navigate to the thread.
            $browser->loginAs($gestor)
                ->visit(route('forum.index', $course))
                ->waitFor('@pin-form-'.$topic->id)
                ->click('@pin-topic-'.$topic->id)
                ->waitFor('@pinned-badge-'.$topic->id)
                ->assertSeeInIgnoringCase('@pinned-badge-'.$topic->id, 'Fixado')
                // Status chip, not a filter: `.ds-chip-info` on a non-focusable
                // `<span>`, never a `<button>` that submits nothing.
                ->assertScript(
                    "document.querySelector('[dusk=\"pinned-badge-{$topic->id}\"]').classList.contains('ds-chip-info')",
                    true
                )
                ->assertScript(
                    "document.querySelector('[dusk=\"pinned-badge-{$topic->id}\"]').tagName",
                    'SPAN'
                );

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'is_pinned' => true,
            ]);

            // 5. Gestor unpins from the thread page.
            $browser->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@pin-topic-'.$topic->id)
                ->click('@pin-topic-'.$topic->id)
                ->waitUntilMissing('@pinned-badge-'.$topic->id);

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'is_pinned' => false,
            ]);
        });
    }

    /**
     * Below `lg` the header action is replaced by the floating action button;
     * the whole creation flow must be reachable with only the FAB.
     */
    public function test_below_the_large_breakpoint_the_fab_replaces_the_header_button_and_publishes_a_topic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        // `assertVisible` only reads CSS visibility, so it cannot see an
        // ancestor that started clipping the `position: fixed` FAB (an
        // `overflow: hidden`, or a `transform`, which also steals its
        // containing block). Asserting the pill sits geometrically inside the
        // viewport is what keeps the mobile create path from dying silently.
        $fabInsideViewport = <<<'JS'
            (function () {
                const rect = document.querySelector('[dusk="new-topic-fab"]').getBoundingClientRect();

                return rect.top >= 0
                    && rect.left >= 0
                    && rect.bottom <= window.innerHeight
                    && rect.right <= window.innerWidth;
            })()
            JS;

        $this->browse(function (Browser $browser) use ($student, $course, $fabInsideViewport): void {
            $browser->resize(...self::MOBILE_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.index', $course))
                ->waitFor('@no-topics')
                ->assertMissing('@new-topic-button')
                ->assertVisible('@new-topic-fab')
                ->assertScript($fabInsideViewport, true)
                ->click('@new-topic-fab')
                ->waitForModalShown('new-topic-modal')
                ->type('@new-topic-title', 'Tópico criado pelo FAB')
                ->type('@new-topic-content', 'Publicado a partir do botão flutuante no mobile.')
                ->click('@new-topic-submit')
                ->waitForText('Tópico criado pelo FAB');

            $browser->resize(...self::DESKTOP_VIEWPORT);
        });

        $this->assertDatabaseHas('forum_topics', [
            'course_id' => $course->id,
            'title' => 'Tópico criado pelo FAB',
            'user_id' => $student->id,
        ]);
    }

    /**
     * The NATURAL 10s interval — no reload and no `fetch` hand-fired from
     * `browser->script()` — delivering a reply created in the background
     * while the student's tab stays open, and the injected card being
     * indistinguishable from a server-rendered one.
     *
     * Slow BY DESIGN: the interval has to actually fire.
     */
    public function test_the_natural_interval_delivers_a_background_reply_with_full_card_parity(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        /** @var User $instructor */
        $instructor = User::factory()->create(['org_id' => $org->id, 'name' => 'Ana Tutora']);
        $instructor->assignRole(RolesEnum::GESTOR->value);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico para teste de polling assíncrono',
            'content' => 'Conteúdo do post principal.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $instructor, $course, $topic): void {
            $browser->resize(...self::DESKTOP_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@replies-list')
                ->assertPresent('[data-forum-polling]')
                // The loop is live before anything exists, so the delivery
                // below can only be explained by the interval itself.
                ->assertScript('window.ForumPolling.timers.size > 0', true);

            $reply = ForumReply::query()->create([
                'topic_id' => $topic->id,
                'user_id' => $instructor->id,
                'content' => 'Resposta assíncrona gerada em segundo plano!',
            ]);

            // Deliberately NOT sanitized: a row inserted any other way is
            // raw, and `appendReply()` must still render it inert.
            $malicious = ForumReply::query()->create([
                'topic_id' => $topic->id,
                'user_id' => $instructor->id,
                'content' => '<script>window.forumPollingXss = true;</script>',
            ]);

            $timestamp = "document.querySelector('[dusk=\"reply-{$reply->id}\"] .text-body-secondary span[title]')";

            $browser->waitFor('@reply-'.$reply->id, 30)
                ->waitFor('@reply-content-'.$reply->id, 10)
                ->assertSee('Resposta assíncrona gerada em segundo plano!')
                // Avatar and role badge parity with `forum/partials/_reply.blade.php`.
                ->assertScript(
                    "document.querySelector('[dusk=\"reply-{$reply->id}\"] .ds-avatar').textContent.trim()",
                    'AT'
                )
                ->assertScript(
                    "document.querySelector('[dusk=\"reply-{$reply->id}\"] .ds-badge').textContent.trim()",
                    'Gestor'
                )
                // Timestamp parity: the absolute stamp only in `title=`, the
                // relative `diffForHumans()` as the visible text.
                ->assertScript("{$timestamp}.title", $reply->created_at->format('d/m/Y H:i'))
                ->assertScript(
                    "(function () { const span = {$timestamp};"
                    ." return span.textContent !== span.title && span.textContent.indexOf('/') === -1; })()",
                    true
                )
                // The reply was created BEFORE this second one, so `data-last-id`
                // must land on the highest id of the batch once the cycle ends.
                ->waitFor('@reply-'.$malicious->id, 30)
                ->assertScript(
                    "document.querySelector('[data-forum-polling]').getAttribute('data-last-id')",
                    (string) $malicious->id
                )
                // Raw markup stays inert: no execution, no parsed child node.
                ->assertScript('window.forumPollingXss === undefined', true)
                ->assertScript(
                    "document.querySelector('[dusk=\"reply-content-{$malicious->id}\"]').children.length",
                    0
                )
                ->assertSeeInIgnoringCase(
                    '@reply-content-'.$malicious->id,
                    'window.forumPollingXss = true;'
                )
                // Action parity with the partial: the injected card carries
                // the viewer-INDEPENDENT "Denunciar" trigger — same dusk
                // selector, same `#report-modal` wiring, same postable pair —
                // and nothing else. "Apagar" is per-viewer and the payload
                // carries no permissions, so its form must NOT be cloned.
                ->assertPresent('@report-reply-'.$reply->id)
                ->assertScript(
                    "(function () {
                        const button = document.querySelector('[dusk=\"report-reply-{$reply->id}\"]');
                        return button.textContent.trim() === 'Denunciar'
                            && button.hasAttribute('data-forum-report-button')
                            && button.getAttribute('data-postable-type') === 'forum_reply'
                            && button.getAttribute('data-postable-id') === '{$reply->id}'
                            && button.getAttribute('data-bs-toggle') === 'modal'
                            && button.getAttribute('data-bs-target') === '#report-modal';
                    })()",
                    true
                )
                ->assertScript(
                    "document.querySelectorAll('[dusk=\"reply-{$reply->id}\"] form').length",
                    0
                )
                ->assertMissing('@delete-reply-'.$reply->id)
                ->assertScript(
                    "document.querySelectorAll('[dusk=\"reply-{$reply->id}\"] [data-bs-toggle=\"modal\"]').length",
                    1
                )
                ->assertPresent('@report-reply-'.$malicious->id);

            $this->assertDatabaseHas('forum_replies', [
                'id' => $reply->id,
                'content' => 'Resposta assíncrona gerada em segundo plano!',
            ]);
        });
    }

    /**
     * The injected card's "Denunciar" is not only a set of attributes. On a
     * reply the browser built itself, Bootstrap has to open the shared
     * `#report-modal` from the injected trigger, `ForumReportModal` has to
     * prefill the hidden `postable_*` fields from
     * `event.relatedTarget.closest('[data-forum-report-button]')`, the
     * `forum-reports.store` POST has to persist the row, and the moderation
     * queue has to list it as a reply — every one of those is a link the
     * attribute assertions above cannot see.
     */
    public function test_a_polled_reply_can_be_reported_through_the_shared_modal_and_reach_the_moderation_queue(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $gestor = $this->gestorFor($org);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico onde uma resposta será denunciada',
            'content' => 'Conteúdo do post principal.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $gestor, $course, $topic): void {
            $browser->resize(...self::DESKTOP_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@replies-list')
                // Nothing to report yet — the card below can only have come
                // from the polling loop, and the page is never reloaded.
                ->assertScript("document.querySelectorAll('[data-reply-id]').length", 0);

            $reply = ForumReply::query()->create([
                'topic_id' => $topic->id,
                'user_id' => $gestor->id,
                'content' => 'Resposta entregue pelo polling e depois denunciada.',
            ]);

            $browser->waitFor('@reply-'.$reply->id, 30)
                ->click('@report-reply-'.$reply->id)
                ->waitForModalShown('report-modal')
                // The prefill ran against the injected trigger: the hidden
                // fields carry THIS reply, so the report can neither be filed
                // against the topic nor stored with a null postable_id.
                ->assertScript("document.querySelector('[data-forum-report-postable-type]').value", 'forum_reply')
                ->assertScript(
                    "document.querySelector('[data-forum-report-postable-id]').value",
                    (string) $reply->id
                )
                ->type('@report-reason', 'Resposta ofensiva que chegou pelo polling.')
                ->click('@report-submit')
                ->waitForText('Denúncia enviada');

            $this->assertDatabaseHas('forum_reports', [
                'postable_type' => ForumReply::class,
                'postable_id' => $reply->id,
                'reported_by' => $student->id,
                'status' => 'pending',
            ]);

            // The queue lists it as a reply report, not a topic one.
            $browser->loginAs($gestor)
                ->visit(route('forum-moderation.index'))
                ->waitForText('Resposta entregue pelo polling e depois denunciada.')
                ->assertSee('Resposta entregue pelo polling e depois denunciada.');
        });
    }

    /**
     * The page burns the whole `throttle:60,1` budget of
     * `forum-replies.fetch` by itself, the following cycles come back 429,
     * and a reply created afterwards must still land — the loop stands down
     * for a few ticks instead of being torn down.
     *
     * Slow BY DESIGN: a full rate-limit window plus the client back-off.
     */
    public function test_the_polling_loop_survives_a_rate_limited_cycle_and_keeps_delivering_replies(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        /** @var User $instructor */
        $instructor = User::factory()->create(['org_id' => $org->id, 'name' => 'Bruno Tutora']);
        $instructor->assignRole(RolesEnum::GESTOR->value);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico para teste de rate limit do polling',
            'content' => 'Conteúdo do post principal.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $instructor, $course, $topic): void {
            $browser->resize(...self::DESKTOP_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@replies-list')
                ->assertPresent('[data-forum-polling]');

            // Burn the budget of this user + route pair with parallel
            // fire-and-forget requests; the wait below polls the counter the
            // page keeps until the limiter has answered 429 at least once.
            $browser->script(<<<'JS'
                (function () {
                    const container = document.querySelector('[data-forum-polling]');
                    const url = container.getAttribute('data-fetch-url');

                    window.__forum429 = 0;

                    for (let i = 0; i < 75; i += 1) {
                        fetch(url + '?since_id=0', {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        }).then((response) => {
                            if (response.status === 429) {
                                window.__forum429 += 1;
                            }
                        }).catch(() => {});
                    }
                })();
                JS);

            $browser->waitUsing(20, 500, fn () => $browser->assertScript('window.__forum429 > 0', true));

            $reply = ForumReply::query()->create([
                'topic_id' => $topic->id,
                'user_id' => $instructor->id,
                'content' => 'Resposta que chega depois de um ciclo limitado.',
            ]);

            // Three skipped cycles at most plus the next successful tick,
            // with margin for the limiter window to roll over.
            $browser->waitFor('@reply-'.$reply->id, 75)
                ->assertSee('Resposta que chega depois de um ciclo limitado.')
                // The 429s stood the loop down; they never cleared it.
                ->assertScript('window.ForumPolling.timers.size > 0', true);
        });
    }

    /**
     * The terminal branch of the failure handler: a topic removed by
     * moderation while the tab is open answers 404 forever, so the loop must
     * END instead of firing a doomed request every 10s until the tab closes.
     * The chain above is its mirror image — a 429 must NEVER clear the
     * interval — and only the two of them together pin the contract down.
     *
     * The status that drove the teardown is captured through a spy on
     * `handleTransportFailure()`: asserting `timers.size === 0` alone would
     * also pass for any other reason the interval might disappear, so the
     * 404 has to be pinned as the cause.
     */
    public function test_a_topic_removed_by_moderation_ends_the_polling_loop(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico removido com a aba aberta',
            'content' => 'Conteúdo do post principal.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $topic): void {
            $browser->resize(...self::DESKTOP_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@replies-list')
                ->assertPresent('[data-forum-polling]')
                ->assertScript('window.ForumPolling.timers.size > 0', true);

            // Re-bind the SAME container at a short interval so the doomed
            // cycle happens quickly. The request below is still fired by the
            // real interval — never by a hand-fired `fetch` from the page.
            // The spy only records which status reached the failure handler;
            // it never intercepts it.
            $browser->script(<<<'JS'
                (function () {
                    window.__forumTeardownStatus = null;

                    const realHandler = window.ForumPolling.handleTransportFailure
                        .bind(window.ForumPolling);

                    window.ForumPolling.handleTransportFailure = function (container, error) {
                        window.__forumTeardownStatus = error ? error.status : null;

                        return realHandler(container, error);
                    };

                    window.ForumPolling.intervalMs = 500;
                    window.ForumPolling.bindContainer(document.querySelector('[data-forum-polling]'));
                })();
                JS);

            // Moderation removes the thread out from under the open tab; the
            // next cycle answers 404 and the interval must be gone.
            $topic->delete();

            $browser->waitUsing(20, 500, fn () => $browser->assertScript('window.ForumPolling.timers.size === 0', true))
                // ...and it was the 404 that ended it, not some other status
                // the page happened to receive along the way.
                ->assertScript('window.__forumTeardownStatus', 404);
        });
    }

    /**
     * The other half of `TERMINAL_STATUSES`. An expired or invalidated
     * session (401), a CSRF-expired page (419) and a revoked access (403)
     * must tear the loop down exactly like the moderation 404 does, while a
     * server briefly broken (a 502 mid-deploy) must NOT.
     *
     * There is no JS unit infrastructure, so the statuses are driven into the
     * real module by stubbing its `httpClient` and re-binding the SAME
     * container: `bindContainer()`/`poll()`/`handleTransportFailure()` below
     * are the production code path — only the network is fake, which is what
     * keeps a doomed 401/419/403 cycle from needing a real session to break.
     */
    public function test_an_expired_session_or_revoked_access_ends_the_loop_while_a_broken_server_does_not(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico para os encerramentos terminais do polling',
            'content' => 'Conteúdo do post principal.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $topic): void {
            $browser->resize(...self::DESKTOP_VIEWPORT)
                ->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@replies-list')
                ->assertPresent('[data-forum-polling]')
                ->assertScript('window.ForumPolling.timers.size > 0', true);

            $browser->script(<<<'JS'
                (function () {
                    window.__forumScriptedStatus = 0;
                    window.__forumScriptedCycles = 0;

                    window.ForumPolling.httpClient = {
                        get() {
                            window.__forumScriptedCycles += 1;

                            const error = new Error(`HTTP error! Status: ${window.__forumScriptedStatus}`);
                            error.status = window.__forumScriptedStatus;

                            return Promise.reject(error);
                        },
                    };
                })();
                JS);

            $cycles = 0;

            foreach ([401, 419, 403] as $status) {
                $cycles += 1;

                $browser->script(
                    "(function () {
                        window.__forumScriptedStatus = {$status};
                        window.ForumPolling.intervalMs = 250;
                        window.ForumPolling.bindContainer(document.querySelector('[data-forum-polling]'));
                    })()"
                );

                // A terminal status ends the loop: the cycle for THIS status
                // has to actually fire and find the interval gone after it —
                // the cycle counter is what keeps the wait from passing on the
                // teardown the previous status already caused.
                $browser->waitUsing(10, 200, fn () => $browser->assertScript(
                    "window.__forumScriptedCycles >= {$cycles} && window.ForumPolling.timers.size === 0",
                    true
                ));
            }

            // The control: a broken server is transient, so several cycles
            // burn through while the interval stays alive and the back-off is
            // what engages instead of a teardown.
            $browser->script(<<<'JS'
                (function () {
                    window.__forumScriptedStatus = 502;
                    window.ForumPolling.intervalMs = 250;
                    window.ForumPolling.bindContainer(document.querySelector('[data-forum-polling]'));
                })();
                JS);

            $browser->waitUsing(10, 200, fn () => $browser->assertScript(
                'window.__forumScriptedCycles >= '.($cycles + 2)
                .' && window.ForumPolling.timers.size > 0'
                ." && (window.ForumPolling.backoffCycles.get(document.querySelector('[data-forum-polling]')) || 0) > 0",
                true
            ));
        });
    }
}
