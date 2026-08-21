@php
    /** @var \App\Models\Organization $organization */
@endphp

<x-ui.field-stack class="max-w-560">
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

    {{--  o logo já persistido é renderizado como imagem resolvida pelo
         disco `public`; nunca o valor cru da coluna. A visibilidade é decidida
         aqui no servidor: sem logo, nenhum `<img>` (e nenhum `src` vazio).
         `.org-logo` é uma classe real, definida em
         `resources/scss/components/_organizations.scss` (preview circular
         pastel da diretriz das telas de admin) — não confundir com a regra homônima e
         isolada de `certificates/pdf.blade.php`, que vive num `<style>`
         inline porque o PDF é renderizado pelo dompdf, fora do pipeline de
         build do app.scss. --}}
    @if($organization->logo_path)
        <div class="mb-3">
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($organization->logo_path) }}"
                 alt="Logo da Organização {{ $organization->name }}"
                 class="org-logo"
                 dusk="organization-logo-preview">
        </div>
    @endif
</x-ui.field-stack>
