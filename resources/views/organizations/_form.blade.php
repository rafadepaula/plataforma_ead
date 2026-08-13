@php
    /** @var \App\Models\Organization $organization */
@endphp

<div class="max-w-560">
    <x-ui.input
        name="name"
        label="Nome"
        required
        value="{{ $organization->name }}"
    />

    <x-ui.input
        name="slug"
        label="Slug"
        value="{{ $organization->slug }}"
        hint="Deixe em branco para gerar automaticamente a partir do Nome."
    />

    <x-ui.input
        name="cnpj"
        label="CNPJ"
        value="{{ $organization->cnpj }}"
        placeholder="00.000.000/0000-00"
        hint="Opcional. Usado apenas para identificação em certificados."
    />

    <x-ui.select
        name="status"
        label="Status"
        required
        :options="['active' => 'Ativo', 'inactive' => 'Inativo']"
        :selected="$organization->status ?? 'active'"
    />

    <x-ui.input
        type="file"
        name="logo"
        label="Logo"
        accept="image/*"
    />

    {{-- UX-004 — o logo já persistido é renderizado como imagem resolvida pelo
         disco `public`; nunca o valor cru da coluna. A visibilidade é decidida
         aqui no servidor: sem logo, nenhum `<img>` (e nenhum `src` vazio).
         `.org-logo` é a exceção histórica de escala de cinza do projeto
         (`resources/scss/app.scss`), a mesma convenção já usada no PDF do
         certificado. --}}
    @if($organization->logo_path)
        <div class="mb-3">
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($organization->logo_path) }}"
                 alt="Logo da Organização {{ $organization->name }}"
                 class="org-logo d-block h-60 mw-100 object-fit-contain border p-2"
                 dusk="organization-logo-preview">
        </div>
    @endif
</div>
