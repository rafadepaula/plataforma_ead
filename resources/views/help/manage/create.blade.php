@extends('layouts.app')

@section('content')
    <x-layout.page-header
        kicker="Suporte e Documentação"
        title="Novo Artigo de Ajuda"
        subtitle="Crie um novo artigo para fornecer suporte contextual em telas ou expandir a wiki."
        :breadcrumb="[
            ['label' => 'Administração'],
            ['label' => 'Artigos de Ajuda', 'url' => route('org.help.artigos.index')],
            ['label' => 'Novo Artigo'],
        ]"
    />

    @include('help.manage._form', [
        'action' => route('org.help.artigos.store'),
        'method' => null,
    ])
@endsection
