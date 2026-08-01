@php
    /** @var \App\Models\Course $course */
@endphp

@extends('layouts.app')

@section('content')
    <x-ui.card title="Novo Link de Convite" kicker="{{ $course->title }}">
        <form method="POST" action="{{ route('courses.invitation-links.store', $course) }}" dusk="invitation-link-form">
            @csrf

            <div style="display: flex; flex-direction: column; gap: 20px; max-width: 480px;">
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
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="invitation-link-submit">Gerar Link</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.invitation-links.index', $course) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
