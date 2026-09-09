@extends('layouts.app')

@section('content')
    <x-layout.page-header
        kicker="Suporte e Documentação"
        title="Editar Artigo de Ajuda"
        subtitle="Atualize as informações, categoria, tela associada ou conteúdo do artigo."
        :breadcrumb="[
            ['label' => 'Administração'],
            ['label' => 'Artigos de Ajuda', 'url' => route('org.help.artigos.index')],
            ['label' => 'Editar Artigo'],
        ]"
    />

    @include('help.manage._form', [
        'action' => route('org.help.artigos.update', $article->id),
        'method' => 'PUT',
    ])
@endsection
