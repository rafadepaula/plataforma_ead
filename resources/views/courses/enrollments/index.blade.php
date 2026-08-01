@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $enrollments */
@endphp

@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">{{ $course->title }}</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Matrículas</h1>
        </div>

        <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
    </div>

    <x-ui.card title="Matricular manualmente" kicker="RF21">
        <form method="POST" action="{{ route('courses.enrollments.store', $course) }}" dusk="manual-enroll-form" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            @csrf

            <div style="flex: 1; min-width: 220px;">
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

            <x-ui.button type="submit" dusk="manual-enroll-submit">Matricular</x-ui.button>
        </form>
    </x-ui.card>

    <div style="margin-top: 20px;">
        <x-ui.table :headers="['Aluno', 'E-mail', 'Status', 'Matriculado em', 'Ações']">
            @forelse($enrollments as $student)
                <tr style="border-bottom: 1px solid var(--color-divider);" dusk="enrollment-row-{{ $student->id }}">
                    <td style="padding: 12px 16px;">{{ $student->name }}</td>
                    <td style="padding: 12px 16px;">{{ $student->email }}</td>
                    <td style="padding: 12px 16px;">
                        <x-ui.badge :variant="$student->pivot->status === 'active' ? 'accent' : 'neutral'">
                            {{ ucfirst($student->pivot->status) }}
                        </x-ui.badge>
                    </td>
                    <td style="padding: 12px 16px;">{{ optional($student->pivot->enrolled_at)->format('d/m/Y') }}</td>
                    <td style="padding: 12px 16px;">
                        @if($student->pivot->status === 'active')
                            <form method="POST" action="{{ route('courses.enrollments.destroy', [$course, $student]) }}" dusk="revoke-enrollment-form-{{ $student->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost" dusk="revoke-enrollment-{{ $student->id }}">Revogar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                        Nenhum aluno matriculado neste Curso.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div style="margin-top: 20px;">
            {{ $enrollments->links() }}
        </div>
    </div>
@endsection
