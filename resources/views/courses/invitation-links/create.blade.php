@php
    /** @var \App\Models\Course $course */
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title, 'url' => route('courses.invitation-links.index', $course)], ['label' => 'Novo Link']]"
        :kicker="$course->title"
        title="Novo Link de Convite"
        subtitle="Gere um link para que alunos se matriculem sozinhos neste curso."
    />

    {{-- `col-lg-6` substitui o antigo `max-width: 480px` do bloco de campos. --}}
    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('courses.invitation-links.store', $course) }}" dusk="invitation-link-form">
                    @csrf

                    <x-ui.field-stack>
                        <x-ui.input
                            type="number"
                            name="max_uses"
                            label="Máximo de usos"
                            hint="Deixe em branco para permitir usos ilimitados."
                            value="{{ old('max_uses') }}"
                        />

                        <x-ui.input
                            type="datetime-local"
                            name="expires_at"
                            label="Expira em"
                            hint="Deixe em branco para nunca expirar."
                            value="{{ old('expires_at') }}"
                        />
                    </x-ui.field-stack>

                    <x-ui.form-actions>
                        <x-ui.button type="submit" dusk="invitation-link-submit">Gerar Link</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('courses.invitation-links.index', $course) }}">Cancelar</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
