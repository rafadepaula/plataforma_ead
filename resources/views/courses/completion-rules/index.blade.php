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
    <x-layout.page-header :kicker="$course->title" title="Regras de Conclusão">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.card title="Nova regra" kicker="UC13">
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
                <x-ui.button type="submit" dusk="completion-rule-submit">Adicionar Regra</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div class="mt-4">
        <x-ui.data-table striped hover responsive :headers="['Tipo', 'Alvo', 'Percentual Exigido', 'Ações']">
            @forelse($rules as $rule)
                <tr dusk="completion-rule-row-{{ $rule->id }}">
                    <td>
                        <x-ui.badge variant="outline">{{ $ruleTypeLabels[$rule->rule_type] ?? $rule->rule_type }}</x-ui.badge>
                    </td>
                    <td>{{ $targetLabelFor($rule) }}</td>
                    <td>{{ $rule->required_percentage }}%</td>
                    <td>
                        <form method="POST" action="{{ route('courses.completion-rules.destroy', [$course, $rule]) }}" dusk="delete-completion-rule-form-{{ $rule->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit"
                                         variant="ghost"
                                         size="sm"
                                         class="text-danger link-danger"
                                         dusk="delete-completion-rule-{{ $rule->id }}">Remover</x-ui.button>
                        </form>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="4" message="Nenhuma regra de conclusão cadastrada para este Curso." />
            @endforelse
        </x-ui.data-table>
    </div>
@endsection
