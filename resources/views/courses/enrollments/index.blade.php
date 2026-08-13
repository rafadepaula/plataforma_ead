@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $enrollments */
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$course->title" title="Matrículas">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.card title="Matricular manualmente" kicker="RF21">
        <form method="POST"
              action="{{ route('courses.enrollments.store', $course) }}"
              dusk="manual-enroll-form"
              class="row g-3 align-items-end">
            @csrf

            <div class="col-12 col-md-4">
                <x-ui.input
                    type="number"
                    name="user_id"
                    label="ID do Usuário"
                    hint="Encontre o ID na listagem de Usuários."
                    value="{{ old('user_id') }}"
                    required
                    dusk="manual-enroll-user-id"
                />
            </div>

            <div class="col-auto mb-3">
                <x-ui.button type="submit" dusk="manual-enroll-submit">Matricular</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div class="mt-4">
        <x-ui.data-table striped hover responsive
                         :headers="['Aluno', 'E-mail', 'Status', 'Matriculado em', 'Ações']">
            @forelse($enrollments as $student)
                <tr dusk="enrollment-row-{{ $student->id }}">
                    <td>{{ $student->name }}</td>
                    <td>{{ $student->email }}</td>
                    <td>
                        <x-ui.badge :variant="$student->pivot->status === 'active' ? 'accent' : 'neutral'">
                            {{ ucfirst($student->pivot->status) }}
                        </x-ui.badge>
                    </td>
                    <td class="text-nowrap">{{ optional($student->pivot->enrolled_at)->format('d/m/Y') }}</td>
                    <td>
                        @if($student->pivot->status === 'active')
                            {{--
                                Form preservado deliberadamente (sem <x-ui.delete-button>):
                                UserManagementTest clica em @revoke-enrollment-{id} esperando
                                submit imediato e afere assertMissing('@revoke-enrollment-form-{id}').
                                Um modal de confirmação quebraria os dois lados do contrato.
                            --}}
                            <form method="POST"
                                  action="{{ route('courses.enrollments.destroy', [$course, $student]) }}"
                                  dusk="revoke-enrollment-form-{{ $student->id }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="ghost"
                                             size="sm"
                                             type="submit"
                                             class="text-danger link-danger"
                                             dusk="revoke-enrollment-{{ $student->id }}">Revogar</x-ui.button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="5" message="Nenhum aluno matriculado neste Curso." />
            @endforelse
        </x-ui.data-table>

        <x-ui.pagination :paginator="$enrollments" />
    </div>
@endsection
