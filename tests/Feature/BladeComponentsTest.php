<?php

namespace Tests\Feature;

use Illuminate\View\ViewException;
use Tests\TestCase;

class BladeComponentsTest extends TestCase
{
    protected function renderBlade(string $template, array $data = []): string
    {
        return (string) $this->blade($template, $data);
    }

    public function test_button_renders_variants_and_classes(): void
    {
        $html = $this->renderBlade('<x-ui.button>Salvar</x-ui.button>');
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('Salvar', $html);

        $html = $this->renderBlade('<x-ui.button variant="tonal">Ação Tonal</x-ui.button>');
        $this->assertStringContainsString('btn btn-tonal ds-tone-primary ds-state-layer', $html);

        $html = $this->renderBlade('<x-ui.button variant="secondary">Secundário</x-ui.button>');
        $this->assertStringContainsString('btn btn-outline-secondary', $html);

        $html = $this->renderBlade('<x-ui.button variant="ghost">Cancelar</x-ui.button>');
        $this->assertStringContainsString('btn btn-ghost ds-state-layer', $html);

        $html = $this->renderBlade('<x-ui.button variant="success">Concluir</x-ui.button>');
        $this->assertStringContainsString('btn btn-success', $html);

        $html = $this->renderBlade('<x-ui.button variant="danger">Excluir</x-ui.button>');
        $this->assertStringContainsString('btn btn-danger', $html);
        $this->assertStringContainsString('lucide-trash', $html);
    }

    public function test_button_sizes_block_and_types(): void
    {
        $sm = $this->renderBlade('<x-ui.button size="sm">Pequeno</x-ui.button>');
        $this->assertStringContainsString('btn-sm', $sm);

        $lg = $this->renderBlade('<x-ui.button size="lg">Grande</x-ui.button>');
        $this->assertStringContainsString('btn-lg', $lg);

        $block = $this->renderBlade('<x-ui.button :block="true">Bloco</x-ui.button>');
        $this->assertStringContainsString('w-100', $block);

        $submit = $this->renderBlade('<x-ui.button type="submit">Enviar</x-ui.button>');
        $this->assertStringContainsString('type="submit"', $submit);
    }

    public function test_button_renders_as_link_when_href_is_provided(): void
    {
        $html = $this->renderBlade('<x-ui.button href="/dashboard" variant="ghost">Painel</x-ui.button>');
        $this->assertStringContainsString('<a href="/dashboard"', $html);
        $this->assertStringContainsString('btn btn-ghost', $html);
        $this->assertStringNotContainsString('<button', $html);

        $disabledHtml = $this->renderBlade('<x-ui.button href="/dashboard" :disabled="true">Painel</x-ui.button>');
        $this->assertStringContainsString('disabled', $disabledHtml);
        $this->assertStringContainsString('aria-disabled="true"', $disabledHtml);
        $this->assertStringContainsString('tabindex="-1"', $disabledHtml);
    }

    public function test_badge_variants_and_sizes(): void
    {
        $primary = $this->renderBlade('<x-ui.badge>Ativo</x-ui.badge>');
        $this->assertStringContainsString('badge ds-badge ds-tone-primary', $primary);

        $critical = $this->renderBlade('<x-ui.badge variant="accent-2">Inativo</x-ui.badge>');
        $this->assertStringContainsString('badge ds-badge ds-tone-critical', $critical);

        $neutral = $this->renderBlade('<x-ui.badge variant="neutral">Rascunho</x-ui.badge>');
        $this->assertStringContainsString('badge ds-badge ds-tone-neutral', $neutral);

        $success = $this->renderBlade('<x-ui.badge variant="success">Aprovado</x-ui.badge>');
        $this->assertStringContainsString('badge ds-badge ds-tone-success', $success);

        $info = $this->renderBlade('<x-ui.badge variant="info">Pendente</x-ui.badge>');
        $this->assertStringContainsString('badge ds-badge ds-tone-info', $info);
    }

    public function test_chip_pressed_state_and_icon(): void
    {
        $pressed = $this->renderBlade('<x-ui.chip :pressed="true" icon="filter">Ativos</x-ui.chip>');
        $this->assertStringContainsString('aria-pressed="true"', $pressed);
        $this->assertStringContainsString('ds-chip ds-state-layer', $pressed);
        $this->assertStringContainsString('Ativos', $pressed);

        $unpressed = $this->renderBlade('<x-ui.chip :pressed="false">Inativos</x-ui.chip>');
        $this->assertStringContainsString('aria-pressed="false"', $unpressed);
    }

    public function test_icon_renders_svg_with_correct_attributes(): void
    {
        $html = $this->renderBlade('<x-ui.icon name="bell" size="20" class="me-2" :stroke-width="2" />');
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('lucide lucide-bell me-2', $html);
        $this->assertStringContainsString('width="20"', $html);
        $this->assertStringContainsString('height="20"', $html);
    }

    public function test_card_surfaces_elevation_and_slots(): void
    {
        $html = $this->renderBlade(
            '<x-ui.card kicker="Informações" title="Meu Card" meta="Atualizado hoje" elevation="md" surface="white">
                <x-slot:image>Banner</x-slot:image>
                <p>Conteúdo do card</p>
                <x-slot:footer>Rodapé do card</x-slot:footer>
            </x-ui.card>'
        );

        $this->assertStringContainsString('card ds-surface shadow', $html);
        $this->assertStringContainsString('Informações', $html);
        $this->assertStringContainsString('Meu Card', $html);
        $this->assertStringContainsString('Conteúdo do card', $html);
    }

    public function test_stat_card_normal_and_no_data_state(): void
    {
        $normal = $this->renderBlade(
            '<x-ui.stat-card kicker="Total Alunos" value="1.250" delta="+12%" caption="Em relação ao mês anterior" icon="user" tone="primary" />'
        );
        $this->assertStringContainsString('card stat-card', $normal);
        $this->assertStringContainsString('Total Alunos', $normal);
        $this->assertStringContainsString('1.250', $normal);

        $noData = $this->renderBlade(
            '<x-ui.stat-card kicker="Conclusões" :no-data="true" />'
        );
        $this->assertStringContainsString('stat-card-value-disabled', $noData);
        $this->assertStringContainsString('—', $noData);
    }

    public function test_table_and_data_table_rendering(): void
    {
        $headers = ['Nome', 'E-mail', 'Ações'];
        $html = $this->renderBlade(
            '<x-ui.table :headers="$headers" striped hover responsive size="sm">
                <tr><td>João</td><td>joao@exemplo.com</td><td>Editar</td></tr>
            </x-ui.table>',
            ['headers' => $headers]
        );

        $this->assertStringContainsString('ds-table-wrap', $html);
        $this->assertStringContainsString('João', $html);
    }

    public function test_progress_progressbar_semantics(): void
    {
        $html = $this->renderBlade(
            '<x-ui.progress :value="75" :max="100" label="Progresso do curso" variant="success" height="8" striped show-label />'
        );

        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuenow="75"', $html);
        $this->assertStringContainsString('style="width: 75%"', $html);
    }

    public function test_alert_variants_aria_and_dismissible(): void
    {
        $danger = $this->renderBlade('<x-ui.alert variant="danger" dismissable>Erro ao salvar dados.</x-ui.alert>');
        $this->assertStringContainsString('role="alert"', $danger);
        $this->assertStringContainsString('alert ds-tone-critical fade show', $danger);
        $this->assertStringContainsString('data-bs-dismiss="alert"', $danger);
    }

    public function test_modal_structure_and_aria(): void
    {
        $html = $this->renderBlade(
            '<x-ui.modal id="user-create" title="Novo Usuário" size="lg" :static="true">
                <p>Formulário aqui</p>
                <x-slot:actions><button class="btn btn-primary">Salvar</button></x-slot:actions>
            </x-ui.modal>'
        );

        $this->assertStringContainsString('modal fade', $html);
        $this->assertStringContainsString('id="user-create"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
    }

    public function test_confirm_modal_and_delete_button(): void
    {
        $html = $this->renderBlade(
            '<x-ui.confirm-modal id="delete-course-1"
                                 title="Excluir Curso"
                                 action="/courses/1"
                                 method="DELETE"
                                 confirm-label="Confirmar exclusão"
                                 message="Deseja realmente remover o curso?" />'
        );

        $this->assertStringContainsString('id="delete-course-1"', $html);
        $this->assertStringContainsString('action="/courses/1"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
    }

    public function test_confirm_modal_submits_external_form_when_form_prop_is_given(): void
    {
        $html = $this->renderBlade(
            '<x-ui.confirm-modal id="submit-attempt-modal"
                                 title="Finalizar prova"
                                 form="quiz-attempt-form"
                                 variant="primary"
                                 confirm-label="Finalizar prova"
                                 confirm-dusk="quiz-attempt-confirm">
                <p>Deseja enviar?</p>
            </x-ui.confirm-modal>'
        );

        $this->assertStringContainsString('id="submit-attempt-modal"', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('form="quiz-attempt-form"', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('dusk="quiz-attempt-confirm"', $html);
    }

    public function test_confirm_modal_fails_loudly_when_neither_action_nor_form_is_given(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('exige `action` (form interno) ou `form`');

        $this->renderBlade(
            '<x-ui.confirm-modal id="delete-course-42" title="Excluir Curso" />'
        );
    }

    public function test_confirm_modal_renders_optional_trigger_slot(): void
    {
        $html = $this->renderBlade(
            '<x-ui.confirm-modal id="delete-course-9" title="Excluir" action="/courses/9">
                <x-slot:trigger>
                    <x-ui.button variant="danger" data-bs-toggle="modal" data-bs-target="#delete-course-9" dusk="delete-course-9-trigger">Excluir</x-ui.button>
                </x-slot:trigger>
            </x-ui.confirm-modal>'
        );

        $this->assertStringContainsString('dusk="delete-course-9-trigger"', $html);
        $this->assertStringContainsString('data-bs-target="#delete-course-9"', $html);
        $this->assertStringContainsString('action="/courses/9"', $html);
    }

    public function test_form_controls_rendering(): void
    {
        $input = $this->renderBlade(
            '<x-ui.input name="email" label="E-mail" type="email" kicker="Credenciais" required />'
        );
        $this->assertStringContainsString('form-floating', $input);
        $this->assertStringContainsString('name="email"', $input);

        $textarea = $this->renderBlade(
            '<x-ui.textarea name="description" label="Descrição" rows="5" required />'
        );
        $this->assertStringContainsString('form-floating', $textarea);
        $this->assertStringContainsString('name="description"', $textarea);

        $options = ['draft' => 'Rascunho', 'published' => 'Publicado'];
        $select = $this->renderBlade(
            '<x-ui.select name="status" label="Status" :options="$options" selected="published" required />',
            ['options' => $options]
        );
        $this->assertStringContainsString('form-floating', $select);
        $this->assertStringContainsString('name="status"', $select);

        $checkbox = $this->renderBlade(
            '<x-ui.checkbox name="terms" label="Aceito os termos" :checked="true" required />'
        );
        $this->assertStringContainsString('form-check', $checkbox);
        $this->assertStringContainsString('type="checkbox"', $checkbox);

        $switch = $this->renderBlade(
            '<x-ui.switch name="is_published" label="Publicar aula" :checked="true" />'
        );
        $this->assertStringContainsString('form-check form-switch', $switch);
        $this->assertStringContainsString('role="switch"', $switch);
    }

    public function test_help_button_renders_fallback_placeholder(): void
    {
        $html = $this->renderBlade('<x-help-button key="unknown.screen" />');
        $this->assertStringContainsString('aria-label="Ajuda"', $html);
        $this->assertStringContainsString('dusk="help-button-unknown.screen"', $html);
    }
}
