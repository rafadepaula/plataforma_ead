@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Support\Collection<int, \App\Models\CourseCompletionRule> $rules */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Module> $modules */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Quiz> $quizzes */

    $ruleTypeLabels = [
        'all_lessons' => 'Todas as Lições',
        'min_quiz_score' => 'Nota Mínima em Quiz',
        'specific_module' => 'Módulo Específico',
    ];

    $ruleTypeIcons = [
        'all_lessons' => 'check',
        'min_quiz_score' => 'award',
        'specific_module' => 'book-open',
    ];

    $targetLabelFor = function (\App\Models\CourseCompletionRule $rule) use ($modules, $quizzes) {
        if ($rule->rule_type === 'min_quiz_score') {
            return $quizzes->firstWhere('id', $rule->target_id)?->title ?? "Quiz #{$rule->target_id}";
        }

        if ($rule->rule_type === 'specific_module') {
            return $modules->firstWhere('id', $rule->target_id)?->title ?? "Módulo #{$rule->target_id}";
        }

        return '—';
    };

    $rulesByType = $rules->groupBy('rule_type');
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title]]"
        :kicker="$course->title"
        title="Regras de Conclusão"
        subtitle="Defina o que um aluno precisa cumprir para receber o certificado deste curso."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.card title="Nova regra" kicker="Regras de Conclusão">
        <form method="POST" action="{{ route('courses.completion-rules.store', $course) }}" dusk="completion-rule-form" class="row g-3 align-items-end">
            @csrf

            <div class="col-12 col-md-4">
                <x-ui.select
                    name="rule_type"
                    label="Tipo de Regra"
                    :options="$ruleTypeLabels"
                    :selected="old('rule_type')"
                    required
                    dusk="completion-rule-type"
                />
            </div>

            <div class="col-12 col-md-4">
                <x-ui.select
                    name="target_id"
                    label="Alvo (quiz ou módulo)"
                    placeholder="Nenhum (Todas as Lições)"
                    :selected="old('target_id')"
                    dusk="completion-rule-target"
                >
                    @if($quizzes->isNotEmpty())
                        <optgroup label="Quizzes">
                            @foreach($quizzes as $quiz)
                                <option value="{{ $quiz->id }}" {{ (string) old('target_id') === (string) $quiz->id ? 'selected' : '' }}>{{ $quiz->title }}</option>
                            @endforeach
                        </optgroup>
                    @endif

                    @if($modules->isNotEmpty())
                        <optgroup label="Módulos">
                            @foreach($modules as $module)
                                <option value="{{ $module->id }}" {{ (string) old('target_id') === (string) $module->id ? 'selected' : '' }}>{{ $module->title }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </x-ui.select>
            </div>

            <div class="col-12 col-md-2">
                <x-ui.input
                    type="number"
                    name="required_percentage"
                    label="Percentual Exigido"
                    value="{{ old('required_percentage', 100) }}"
                    required
                    dusk="completion-rule-percentage"
                />
            </div>

            <div class="col-12 col-md-auto mb-3">
                <x-ui.button type="submit" icon="plus" dusk="completion-rule-submit">Adicionar Regra</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.alert variant="info" class="mt-4">
        <strong>Todas</strong> as regras ativas abaixo precisam ser satisfeitas ao mesmo tempo para que o
        certificado seja emitido — a validação é sempre "E", nunca "OU".
    </x-ui.alert>

    <div class="mt-4 d-flex flex-column gap-4">
        @foreach($ruleTypeLabels as $type => $label)
            @php
                $rulesOfType = $rulesByType->get($type, collect());
            @endphp
            <x-ui.card :kicker="$rulesOfType->isNotEmpty() ? 'Ativa' : 'Inativa'">
                <x-slot:titleSlot>
                    <div class="d-flex align-items-center gap-2">
                        <x-ui.icon :name="$ruleTypeIcons[$type]" size="18" aria-hidden="true" />
                        {{ $label }}
                    </div>
                </x-slot:titleSlot>

                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <x-ui.switch
                        :name="'rule_active_'.$type"
                        :label="$rulesOfType->isNotEmpty() ? 'Regra ativa para este curso' : 'Nenhuma regra deste tipo cadastrada'"
                        :checked="$rulesOfType->isNotEmpty()"
                        disabled
                        help="Adicione ou remova regras usando o formulário acima e a lista abaixo."
                    />
                </div>

                @forelse($rulesOfType as $rule)
                    <div dusk="completion-rule-row-{{ $rule->id }}"
                         class="d-flex align-items-center justify-content-between gap-3 py-2 border-top">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $targetLabelFor($rule) }}</div>
                            <div class="small text-body-secondary">Limiar exigido: {{ $rule->required_percentage }}%</div>
                        </div>

                        {{--
                            Form preservado deliberadamente (sem <x-ui.confirm-modal>):
                            CourseCompletionRuleTest clica em @delete-completion-rule-{id}
                            esperando remoção imediata, no mesmo padrão documentado para
                            matrículas e links de convite — inserir um passo de confirmação
                            quebraria a jornada única que o teste afere.
                        --}}
                        <form method="POST" action="{{ route('courses.completion-rules.destroy', [$course, $rule]) }}" dusk="delete-completion-rule-form-{{ $rule->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit"
                                         variant="danger"
                                         icon="trash"
                                         dusk="delete-completion-rule-{{ $rule->id }}">Remover</x-ui.button>
                        </form>
                    </div>
                @empty
                    <p class="small text-body-secondary mb-0">Nenhuma regra deste tipo cadastrada para este curso.</p>
                @endforelse
            </x-ui.card>
        @endforeach
    </div>
@endsection
