@extends('layouts.app')

@section('content')
    <x-layout.page-header
        kicker="Suporte e Documentação"
        title="Artigos de Ajuda"
        subtitle="Gerencie os artigos de ajuda contextual e da wiki pública da plataforma."
        :breadcrumb="[
            ['label' => 'Administração'],
            ['label' => 'Artigos de Ajuda'],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('help.index') }}" class="btn btn-outline-secondary" target="_blank">
                <x-ui.icon name="external-link" size="16" class="me-1" />
                <span>Ver Centro de Ajuda</span>
            </a>
            @can('create', App\Models\HelpArticle::class)
                <a href="{{ route('org.help.artigos.create') }}" class="btn btn-primary" dusk="new-help-article">
                    <x-ui.icon name="plus" size="16" class="me-1" />
                    <span>Novo Artigo</span>
                </a>
            @endcan
        </x-slot:actions>
    </x-layout.page-header>

    @if($articles->isEmpty())
        <x-ui.empty-state
            icon="help-circle"
            title="Nenhum artigo de ajuda cadastrado"
            message="Cadastre o primeiro artigo para fornecer suporte contextual e alimentar a wiki."
        >
            @can('create', App\Models\HelpArticle::class)
                <x-slot:action>
                    <a href="{{ route('org.help.artigos.create') }}" class="btn btn-primary">
                        Cadastrar Artigo
                    </a>
                </x-slot:action>
            @endcan
        </x-ui.empty-state>
    @else
        <x-ui.card surface="white" class="border">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Título</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Tela Associada</th>
                            <th scope="col">Escopo</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($articles as $article)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-primary">{{ $article->title }}</div>
                                    <small class="text-body-secondary">slug: {{ $article->slug }}</small>
                                </td>
                                <td>
                                    <x-ui.badge variant="neutral" :dot="false">{{ $article->category }}</x-ui.badge>
                                </td>
                                <td>
                                    @if($article->target_page_key)
                                        <code>{{ $article->target_page_key }}</code>
                                    @else
                                        <span class="text-body-secondary small">Apenas wiki</span>
                                    @endif
                                </td>
                                <td>
                                    @if($article->org_id === null)
                                        <x-ui.badge variant="info" :dot="false">Global</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="accent" :dot="false">
                                            {{ $article->organization?->name ?? 'Org #'.$article->org_id }}
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('help.show', $article->slug) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="Visualizar na Wiki">
                                            <x-ui.icon name="eye" size="14" />
                                        </a>
                                        @can('update', $article)
                                            <a href="{{ route('org.help.artigos.edit', $article->id) }}" class="btn btn-sm btn-outline-primary" dusk="edit-help-article-{{ $article->id }}" title="Editar">
                                                <x-ui.icon name="edit" size="14" />
                                            </a>
                                        @endcan
                                        @can('delete', $article)
                                            <form method="POST" action="{{ route('org.help.artigos.destroy', $article->id) }}" class="d-inline" onsubmit="return confirm('Deseja realmente excluir este artigo?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" dusk="delete-help-article-{{ $article->id }}" title="Excluir">
                                                    <x-ui.icon name="trash" size="14" />
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
@endsection
