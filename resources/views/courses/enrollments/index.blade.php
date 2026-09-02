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

            {{--
                Único gatilho primary da tela (convenção do page-header),
                abrindo a modal de matrícula de forma 100% declarativa
                (`data-bs-*`, sem JS artesanal) — mesmo padrão de
                `forum/index` e `certificates/index`.
            --}}
            <x-ui.button variant="primary"
                         icon="plus"
                         data-bs-toggle="modal"
                         data-bs-target="#enroll-student-modal"
                         dusk="enroll-student-button">Matricular aluno</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{--
        Busca client-side ainda não filtra no servidor: `EnrollmentController`
        fica fora do escopo deste bucket (não está na lista de arquivos
        editáveis), então o campo aqui é aditivo/visual por ora — o mesmo
        padrão de "backend pendente, view já preparada" descrito para as
        métricas novas do dashboard. `dense` enxuga o padding do card para
        aproveitar melhor o espaço vertical; o default do componente
        permanece o padrão das demais telas.
    --}}
    <x-ui.filter-bar
        :action="route('courses.enrollments.index', $course)"
        method="GET"
        submit-label="Buscar"
        :reset-url="route('courses.enrollments.index', $course)"
        label="Filtrar matrículas"
        dense
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
                @php
                    // `CourseUser` (pivot custom via `->using()`) já entrega
                    // `enrolled_at` como Carbon — antes do pivot class a
                    // célula rendia sempre vazia (`->format()` sobre string).
                @endphp
                <td class="text-nowrap ds-tabular-nums" data-label="Matriculado em">{{ $student->pivot->enrolled_at?->format('d/m/Y') ?? '—' }}</td>
                <td data-label="Ações">
                    @if($student->pivot->status === 'active')
                        {{--
                            O form é o alvo externo da `x-ui.confirm-modal`
                            renderizada fora da tabela (HTML não permite form
                            aninhado, e a modal precisaria ficar fora do
                            `.table-responsive` para não ser recortada). O
                            botão aqui é só gatilho declarativo; a confirmação
                            submete este form pelo atributo `form=`. Contrato:
                            `revoke-enrollment-form-{id}`/`revoke-enrollment-{id}`
                            estão congelados no snapshot e no `UserManagementTest`.
                        --}}
                        <form id="revoke-enrollment-form-{{ $student->id }}"
                              method="POST"
                              action="{{ route('courses.enrollments.destroy', [$course, $student]) }}"
                              dusk="revoke-enrollment-form-{{ $student->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="danger"
                                         size="sm"
                                         type="button"
                                         icon="x"
                                         data-bs-toggle="modal"
                                         data-bs-target="#revoke-enrollment-modal-{{ $student->id }}"
                                         dusk="revoke-enrollment-{{ $student->id }}">Revogar</x-ui.button>
                        </form>
                    @elseif($student->pivot->status === 'cancelled')
                        {{--
                            Ação construtiva: submit direto, sem confirmação —
                            contraste deliberado com "Revogar". Restaura
                            preservando progresso e `enrolled_at` original.
                        --}}
                        <form method="POST"
                              action="{{ route('courses.enrollments.restore', [$course, $student]) }}"
                              dusk="restore-enrollment-form-{{ $student->id }}">
                            @csrf
                            <x-ui.button variant="success"
                                         size="sm"
                                         type="submit"
                                         icon="check"
                                         dusk="restore-enrollment-{{ $student->id }}">Restaurar</x-ui.button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <x-ui.empty-state
                colspan="5"
                icon="user"
                title="Nenhum aluno matriculado neste curso."
                description="Use o botão \"Matricular aluno\" para buscar o aluno por nome, e-mail ou CPF, ou \"Cadastrar novo aluno\" para criar a conta e já matriculá-la."
            />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$enrollments" />

    {{--
        Uma modal de confirmação por matrícula ativa, FORA da tabela:
        modais dentro do wrapper responsivo são recortadas por ele
        (convenção documentada em `gestor/students/index`). Ligada ao form
        da linha pela prop `form=` — o botão "Revogar, confirmar" submete
        o `revoke-enrollment-form-{id}` externo via atributo `form`.
    --}}
    @foreach ($enrollments as $student)
        @if ($student->pivot->status === 'active')
            <x-ui.confirm-modal
                id="revoke-enrollment-modal-{{ $student->id }}"
                title="Revogar matrícula"
                form="revoke-enrollment-form-{{ $student->id }}"
                method="DELETE"
                confirm-label="Revogar"
                message="Revogar matrícula de {{ $student->name }}? O acesso ao curso será cancelado."
                confirm-dusk="revoke-enrollment-confirm-{{ $student->id }}" />
        @endif
    @endforeach

    {{--
        Modal de matrícula: abriga o form que antes morava no card
        removido. `size="lg"` dá fôlego ao autocomplete (nome + e-mail +
        badge de reativação na mesma linha). Markup e seletores PRESERVADOS
        do card original —
        `EnrollmentSearch.js` dá bind no load e os seletores
        `manual-enroll-form`/`manual-enroll-search`/`manual-enroll-results`/
        `manual-enroll-user-id`/`manual-enroll-selected`/`manual-enroll-submit`
        são congelados no snapshot e no `UserManagementTest` — não renomear.
        Os resultados da busca renderizam EM FLUXO (não `position-absolute`):
        empurram o conteúdo para baixo em vez de sobrepor o rodapé da modal.
    --}}
    <x-ui.modal id="enroll-student-modal" title="Matricular aluno" size="lg">
        <form method="POST"
              action="{{ route('courses.enrollments.store', $course) }}"
              dusk="manual-enroll-form"
              data-enrollment-search
              data-search-url="{{ route('courses.enrollments.search', $course) }}"
              class="row g-3 align-items-end">
            @csrf

            <div class="col-12">
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
                <div class="list-group mt-2"
                     dusk="manual-enroll-results"
                     data-enrollment-search-results
                     hidden></div>
            </div>

            <input type="hidden" name="user_id" dusk="manual-enroll-user-id" data-enrollment-user-id value="{{ old('user_id') }}">

            <div class="col-12" dusk="manual-enroll-selected" data-enrollment-selected hidden></div>

            @error('user_id')
                <div class="col-12">
                    <div class="small text-danger" role="alert">{{ $message }}</div>
                </div>
            @enderror

            <div class="col-12">
                <x-ui.button type="submit" dusk="manual-enroll-submit" data-enrollment-submit disabled>Matricular</x-ui.button>
            </div>
        </form>

        <x-slot:actions>
            <x-ui.button variant="ghost"
                         size="sm"
                         icon="plus"
                         :href="route('courses.enrollments.create', $course)"
                         dusk="create-student-link">Cadastrar novo aluno</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
@endsection

@push('scripts')
    @if ($errors->any())
        <script>
            // Reabre a modal de matrícula quando o POST volta com erro de
            // validação: sem isso a mensagem de validação e o `old()`
            // ficariam escondidos atrás da modal fechada. `window.bootstrap`
            // é montado por `app.js`; `getOrCreateInstance` (nunca `new`) é
            // o padrão de `AuditLogDiffModal`/`ForumReportModal`.
            document.addEventListener('DOMContentLoaded', function () {
                window.bootstrap.Modal.getOrCreateInstance(
                    document.getElementById('enroll-student-modal')
                ).show();
            });
        </script>
    @endif
@endpush
