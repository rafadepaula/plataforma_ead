@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Course> $courses */
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[
            ['label' => 'Organização'],
            ['label' => 'Alunos Matriculados', 'url' => route('gestor.students.index')],
            ['label' => 'Cadastrar aluno'],
        ]"
        kicker="Organização"
        title="Cadastrar aluno"
        subtitle="Crie a conta do aluno, matricule-a no curso escolhido e receba o link de convite único para ela definir a própria senha."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('gestor.students.index') }}">Voltar aos Alunos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST"
                      action="{{ route('gestor.students.store') }}"
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
                            hint="Com ou sem máscara (000.000.000-00)."
                        />

                        <x-ui.select
                            name="course_id"
                            label="Curso"
                            required
                            :options="$courses->pluck('title', 'id')->all()"
                            :selected="old('course_id')"
                            placeholder="Selecione o curso"
                            hint="O aluno já será matriculado neste curso."
                            dusk="create-student-course"
                        />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" href="{{ route('gestor.students.index') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="create-student-submit">Cadastrar e Matricular</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
