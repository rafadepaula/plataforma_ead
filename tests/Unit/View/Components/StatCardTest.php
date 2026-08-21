<?php

namespace Tests\Unit\View\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StatCardTest extends TestCase
{
    public function test_it_renders_toned_icon_plain_delta_caption_and_forwarded_attributes(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.stat-card
                kicker="Certificados emitidos"
                value="112"
                delta="+12%"
                delta-variant="success"
                caption="no período"
                icon="award"
                tone="secondary"
                class="h-100"
                dusk="stat-certificates-issued"
            />
        BLADE);

        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="[^"]*\bstat-card\b[^"]*\bh-100\b[^"]*"[^>]*dusk="stat-certificates-issued"/',
            $html,
        );
        $this->assertStringContainsString('stat-card-icon-secondary', $html);
        $this->assertStringContainsString('ds-overline', $html);
        $this->assertStringNotContainsString('shadow-sm', $html);
        $this->assertStringContainsString('ds-tone-success', $html);
        $this->assertStringContainsString('ds-badge-plain', $html);
        $this->assertStringContainsString('+12%', $html);
        $this->assertStringContainsString('no período', $html);
    }

    public function test_it_renders_caption_without_delta_and_marks_an_unavailable_value(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.stat-card
                kicker="Taxa de conclusão"
                value="63%"
                no-data
                icon="check"
                tone="tertiary"
            />
        BLADE);

        $this->assertStringContainsString('stat-card-icon-tertiary', $html);
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bstat-card-value\b[^"]*\bstat-card-value-disabled\b[^"]*"[^>]*>\s*—\s*</',
            $html,
        );
        $this->assertStringContainsString('Sem dados no período', $html);
        $this->assertStringNotContainsString('class="badge', $html);
    }

    public function test_no_data_caption_takes_precedence_over_a_regular_caption(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.stat-card kicker="Certificados emitidos" value="0" caption="emitidos no total" no-data />
        BLADE);

        $this->assertStringContainsString('Sem dados no período', $html);
        $this->assertStringNotContainsString('emitidos no total', $html);
    }

    public function test_it_preserves_the_existing_default_api(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.stat-card kicker="Alunos ativos" value="248" delta="0" icon="user" />
        BLADE);

        $this->assertStringContainsString('stat-card-icon-primary', $html);
        $this->assertStringContainsString('ds-tone-primary', $html);
        $this->assertStringContainsString('>0<', preg_replace('/\s+/', '', $html));
    }

    public function test_it_renders_a_negative_delta_with_the_neutral_tone(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.stat-card kicker="Alunos ativos" value="24" delta="-3,0%" delta-variant="neutral" />
        BLADE);

        $this->assertStringContainsString('ds-tone-neutral', $html);
        $this->assertStringContainsString('-3,0%', $html);
    }
}
