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

    $targetLabelFor = function (\App\Models\CourseCompletionRule $rule) use ($modules, $quizzes) {
        if ($rule->rule_type === 'min_quiz_score') {
            return $quizzes->firstWhere('id', $rule->target_id)?->title ?? "Quiz #{$rule->target_id}";
        }

        if ($rule->rule_type === 'specific_module') {
            return $modules->firstWhere('id', $rule->target_id)?->title ?? "Módulo #{$rule->target_id}";
        }

        return '—';
    };
@endphp

@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div>
                <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">{{ $course->title }}</span>
                <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Regras de Conclusão</h1>
            </div>
        </div>

        <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
    </div>

    <x-ui.card title="Nova regra" kicker="UC13">
        <form method="POST" action="{{ route('courses.completion-rules.store', $course) }}" dusk="completion-rule-form" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            @csrf

            <div style="flex: 1; min-width: 220px;">
                <x-ui.select
                    name="rule_type"
                    label="Tipo de Regra"
                    :options="$ruleTypeLabels"
                    :selected="old('rule_type')"
                    required
                    dusk="completion-rule-type"
                />
            </div>

            <div style="flex: 1; min-width: 220px;">
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

            <div style="flex: 1; min-width: 160px;">
                <x-ui.input
                    type="number"
                    name="required_percentage"
                    label="Percentual Exigido"
                    value="{{ old('required_percentage', 100) }}"
                    required
                    dusk="completion-rule-percentage"
                />
            </div>

            <x-ui.button type="submit" dusk="completion-rule-submit">Adicionar Regra</x-ui.button>
        </form>
    </x-ui.card>

    <div style="margin-top: 20px;">
        <x-ui.table :headers="['Tipo', 'Alvo', 'Percentual Exigido', 'Ações']">
            @forelse($rules as $rule)
                <tr style="border-bottom: 1px solid var(--color-divider);" dusk="completion-rule-row-{{ $rule->id }}">
                    <td style="padding: 12px 16px;">
                        <x-ui.badge variant="outline">{{ $ruleTypeLabels[$rule->rule_type] ?? $rule->rule_type }}</x-ui.badge>
                    </td>
                    <td style="padding: 12px 16px;">{{ $targetLabelFor($rule) }}</td>
                    <td style="padding: 12px 16px;">{{ $rule->required_percentage }}%</td>
                    <td style="padding: 12px 16px;">
                        <form method="POST" action="{{ route('courses.completion-rules.destroy', [$course, $rule]) }}" dusk="delete-completion-rule-form-{{ $rule->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost" dusk="delete-completion-rule-{{ $rule->id }}">Remover</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                        Nenhuma regra de conclusão cadastrada para este Curso.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </div>
@endsection
