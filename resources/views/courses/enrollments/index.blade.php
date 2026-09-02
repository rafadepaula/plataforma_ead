@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $enrollments */

    $statusLabel = [
        'active' => 'Ativo',
        'cancelled' => 'Cancelado',
        'completed' => 'Concluído',
    ];

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title]]"
        :kicker="$course->title"
        title="Matrículas"
        subtitle="Acompanhe quem está matriculado neste curso e revogue acessos quando necessário."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.card title="Matricular aluno" kicker="Matrículas">
        <div class="d-flex justify-content-end mb-3">
            <x-ui.button variant="tonal"
                         size="sm"
                         icon="plus"
                         :href="route('courses.enrollments.create', $course)"
                         dusk="create-student-link">Cadastrar novo aluno</x-ui.button>
        </div>

        {{--
            `EnrollmentSearch.js` drive este formulário: a busca por
            nome/e-mail/CPF preenche o `user_id` oculto que
            `StoreEnrollmentRequest` sempre esperou — o contrato POST
            (incluindo reativação de matrícula cancelada) não muda.
            `manual-enroll-form`/`manual-enroll-submit`/`manual-enroll-user-id`
            são seletores Dusk congelados no snapshot e no
            `UserManagementTest` — não renomear.
        --}}
        <form method="POST"
              action="{{ route('courses.enrollments.store', $course) }}"
              dusk="manual-enroll-form"
              data-enrollment-search
              data-search-url="{{ route('courses.enrollments.search', $course) }}"
              class="row g-3 align-items-end">
            @csrf

            <div class="col-12 col-md-6 position-relative">
                <x-ui.input
                    type="search"
                    name="enrollment_search"
                    label="Buscar aluno por nome, e-mail ou CPF"
                    hint="Mínimo de 2 caracteres. Alunos já ativos neste curso não aparecem."
                    value="{{ old('enrollment_search') }}"
                    autocomplete="off"
                    dusk="manual-enroll-search"
                    data-enrollment-search-input
                />
                <div class="list-group position-absolute w-100 shadow-sm"
                     style="z-index: 1055;"
                     dusk="manual-enroll-results"
                     data-enrollment-search-results
                     hidden></div>
            </div>

            <input type="hidden" name="user_id" dusk="manual-enroll-user-id" data-enrollment-user-id value="{{ old('user_id') }}">

            <div class="col-12 col-md-6" dusk="manual-enroll-selected" data-enrollment-selected hidden></div>

            @error('user_id')
                <div class="col-12">
                    <div class="small text-danger" role="alert">{{ $message }}</div>
                </div>
            @enderror

            <div class="col-auto mb-3">
                <x-ui.button type="submit" dusk="manual-enroll-submit" data-enrollment-submit disabled>Matricular</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{--
        Busca client-side ainda não filtra no servidor: `EnrollmentController`
        fica fora do escopo deste bucket (não está na lista de arquivos
        editáveis), então o campo aqui é aditivo/visual por ora — o mesmo
        padrão de "backend pendente, view já preparada" descrito para as
        métricas novas do dashboard.
    --}}
    <x-ui.filter-bar
        :action="route('courses.enrollments.index', $course)"
        method="GET"
        submit-label="Buscar"
        :reset-url="route('courses.enrollments.index', $course)"
        label="Filtrar matrículas"
        class="mt-4"
    >
        <div class="col-12 col-md-6">
            <x-ui.input
                type="search"
                name="search"
                label="Nome ou e-mail"
                value="{{ request('search') }}"
            />
        </div>
    </x-ui.filter-bar>

    <div class="mt-4">
        <x-ui.data-table striped hover responsive
                         :headers="['Aluno', 'Progresso', 'Status', 'Matriculado em', 'Ações']">
            @forelse($enrollments as $student)
                <tr dusk="enrollment-row-{{ $student->id }}">
                    <td data-label="Aluno">
                        <div class="d-flex align-items-center gap-3">
                            <x-ui.avatar :initials="$initialsFor($student->name)" />
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $student->name }}</div>
                                <div class="ds-caption text-body-secondary text-truncate">{{ $student->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="ds-tabular-nums" data-label="Progresso">
                        <x-ui.progress :value="$student->pivot->progress_percentage" show-label />
                    </td>
                    <td data-label="Status">
                        <x-ui.badge :variant="$student->pivot->status === 'cancelled' ? 'neutral' : 'success'">
                            {{ $statusLabel[$student->pivot->status] ?? ucfirst($student->pivot->status) }}
                        </x-ui.badge>
                    </td>
                    <td class="text-nowrap ds-tabular-nums" data-label="Matriculado em">{{ optional($student->pivot->enrolled_at)->format('d/m/Y') }}</td>
                    <td data-label="Ações">
                        @if($student->pivot->status === 'active')
                            {{--
                                Form preservado deliberadamente (sem <x-ui.delete-button>/
                                <x-ui.confirm-modal>): UserManagementTest clica em
                                @revoke-enrollment-{id} esperando submit imediato e afere
                                assertMissing('@revoke-enrollment-form-{id}'). Um modal de
                                confirmação quebraria os dois lados do contrato.
                            --}}
                            <form method="POST"
                                  action="{{ route('courses.enrollments.destroy', [$course, $student]) }}"
                                  dusk="revoke-enrollment-form-{{ $student->id }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="danger"
                                             size="sm"
                                             type="submit"
                                             icon="x"
                                             dusk="revoke-enrollment-{{ $student->id }}">Revogar</x-ui.button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-ui.empty-state
                    colspan="5"
                    icon="user"
                    title="Nenhum aluno matriculado neste curso."
                    description="Busque o aluno por nome, e-mail ou CPF no formulário acima, ou use \"Cadastrar novo aluno\" para criar a conta e já matriculá-la."
                />
            @endforelse
        </x-ui.data-table>

        <x-ui.pagination :paginator="$enrollments" />
    </div>
@endsection
