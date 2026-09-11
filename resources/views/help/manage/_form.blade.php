<form method="POST" action="{{ $action }}" dusk="org-help-article-form">
    @csrf
    @if($method ?? false)
        @method($method)
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <x-ui.card surface="white" class="border p-4 mb-4">
                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold">Título do Artigo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        class="form-control @error('title') is-invalid @enderror"
                        value="{{ old('title', $article->title) }}"
                        required
                        placeholder="Ex: Como criar e organizar lições do curso"
                    >
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label fw-semibold">Slug (URL amigável) <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="slug"
                        id="slug"
                        class="form-control @error('slug') is-invalid @enderror"
                        value="{{ old('slug', $article->slug) }}"
                        required
                        placeholder="Ex: como-criar-e-organizar-licoes"
                    >
                    <small class="form-text text-body-secondary">Identificador único utilizado na URL da wiki pública.</small>
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="content" class="form-label fw-semibold mb-0">Conteúdo (Markdown) <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#previewModal" id="btn-open-preview">
                            <x-ui.icon name="eye" size="14" />
                            <span>Pré-visualizar</span>
                        </button>
                    </div>
                    <textarea
                        name="content"
                        id="content"
                        rows="15"
                        class="form-control font-monospace @error('content') is-invalid @enderror"
                        required
                        placeholder="Escreva o artigo em Markdown. Você pode usar títulos (##), listas (-), **negrito**, etc."
                    >{{ old('content', $article->content) }}</textarea>
                    <small class="form-text text-body-secondary">Suporta formatação Markdown padrão. Tags HTML serão sanitizadas.</small>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </x-ui.card>
        </div>

        <div class="col-12 col-lg-4">
            <x-ui.card surface="white" class="border p-4 mb-4">
                <h2 class="h6 fw-bold mb-3 pb-2 border-bottom">Classificação e Escopo</h2>

                <div class="mb-3">
                    <label for="category" class="form-label fw-semibold">Categoria <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="category"
                        id="category"
                        list="categories-list"
                        class="form-control @error('category') is-invalid @enderror"
                        value="{{ old('category', $article->category) }}"
                        required
                        placeholder="Ex: Cursos, Alunos, Avaliações..."
                    >
                    <datalist id="categories-list">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}"></option>
                        @endforeach
                    </datalist>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="target_page_key" class="form-label fw-semibold">Tela do Sistema (target_page_key)</label>
                    <input
                        type="text"
                        name="target_page_key"
                        id="target_page_key"
                        list="target-page-keys-list"
                        class="form-control @error('target_page_key') is-invalid @enderror"
                        value="{{ old('target_page_key', $article->target_page_key) }}"
                        placeholder="Ex: courses.index, classroom.show..."
                    >
                    <datalist id="target-page-keys-list">
                        @foreach($targetPageKeys as $key)
                            <option value="{{ $key }}"></option>
                        @endforeach
                    </datalist>
                    <small class="form-text text-body-secondary">Deixe em branco se for um artigo livre apenas para a wiki.</small>
                    @error('target_page_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="audience" class="form-label fw-semibold">Público (Quem Pode Ver)</label>
                    <select name="audience" id="audience" class="form-select @error('audience') is-invalid @enderror">
                        @foreach(\App\Enums\Help\HelpAudienceEnum::cases() as $audienceOption)
                            <option value="{{ $audienceOption->value }}" @selected(old('audience', $article->audience?->value ?? 'aluno') === $audienceOption->value)>
                                {{ match($audienceOption) {
                                    \App\Enums\Help\HelpAudienceEnum::ALUNO => 'Público (todos, incluindo alunos e visitantes)',
                                    \App\Enums\Help\HelpAudienceEnum::PROFESSOR => 'Professores (e Admins)',
                                    \App\Enums\Help\HelpAudienceEnum::GESTOR => 'Gestores (e Admins)',
                                    \App\Enums\Help\HelpAudienceEnum::ADMIN => 'Somente Admins',
                                } }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-body-secondary">Define quem vê este artigo na Central de Ajuda.</small>
                    @error('audience')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @if($organizations->isNotEmpty())
                    <div class="mb-3">
                        <label for="org_id" class="form-label fw-semibold">Organização (Escopo)</label>
                        <select name="org_id" id="org_id" class="form-select @error('org_id') is-invalid @enderror">
                            <option value="" @selected(old('org_id', $article->org_id) === null)>Global (Todas as organizações)</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" @selected(old('org_id', $article->org_id) == $org->id)>
                                    {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-body-secondary">Artigos de organização têm precedência sobre os globais na tela daquela organização.</small>
                        @error('org_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <div class="d-grid gap-2 pt-3 border-top">
                    <button type="submit" class="btn btn-primary" dusk="save-help-article">
                        Salvar Artigo
                    </button>
                    <a href="{{ route('org.help.artigos.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </x-ui.card>
        </div>
    </div>
</form>

{{-- Modal de Pré-visualização --}}
<x-ui.modal id="previewModal" title="Pré-visualização do Artigo" size="xl">
    <div dusk="org-help-article-preview" class="ds-prose p-3" id="help-preview-body">
        <p class="text-body-secondary">Carregando pré-visualização...</p>
    </div>

    <x-slot:actions>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
    </x-slot:actions>
</x-ui.modal>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');

    if (titleInput && slugInput && !slugInput.value) {
        titleInput.addEventListener('input', function () {
            if (!slugInput.dataset.manual) {
                slugInput.value = titleInput.value
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)/g, '');
            }
        });
        slugInput.addEventListener('input', function () {
            slugInput.dataset.manual = 'true';
        });
    }

    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('show.bs.modal', async function () {
            const content = document.getElementById('content')?.value || '';
            const previewBody = document.getElementById('help-preview-body');
            if (!previewBody) return;

            previewBody.innerHTML = '<p class="text-body-secondary">Gerando pré-visualização...</p>';

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('{{ route('org.help.preview') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                    },
                    body: JSON.stringify({ content: content })
                });

                if (response.ok) {
                    const data = await response.json();
                    previewBody.innerHTML = data.html || '<p class="text-body-secondary">Nenhum conteúdo digitado.</p>';
                } else {
                    previewBody.innerHTML = '<p class="text-danger">Erro ao gerar pré-visualização.</p>';
                }
            } catch (err) {
                previewBody.innerHTML = '<p class="text-danger">Erro de conexão com o servidor.</p>';
            }
        });
    }
});
</script>
