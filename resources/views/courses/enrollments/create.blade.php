@php
    /** @var \App\Models\Course $course */
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[
            ['label' => 'Cursos', 'url' => route('courses.index')],
            ['label' => $course->title, 'url' => route('courses.enrollments.index', $course)],
            ['label' => 'Matrículas', 'url' => route('courses.enrollments.index', $course)],
            ['label' => 'Novo Aluno'],
        ]"
        :kicker="$course->title"
        title="Cadastrar novo aluno"
        subtitle="Crie a conta do aluno e matricule-a neste curso em uma única etapa. A senha inicial do aluno será o CPF informado."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('courses.enrollments.index', $course)">Voltar às Matrículas</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST"
                      action="{{ route('courses.enrollments.store-student', $course) }}"
                      dusk="create-student-form">
                    @csrf

                    <x-ui.field-stack>
                        <x-ui.input name="name" label="Nome" required value="{{ old('name') }}" />

                        <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email') }}" />

                        <x-ui.input
                            name="cpf"
                            label="CPF"
                            required
                            value="{{ old('cpf') }}"
                            hint="Com ou sem máscara (000.000.000-00). Também será a senha inicial do aluno."
                        />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" :href="route('courses.enrollments.index', $course)">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="create-student-submit">Cadastrar e Matricular</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
